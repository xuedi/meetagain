<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Activity\Messages\ChangedUsername;
use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\HealthInterface;
use Override;

readonly class TracesAnonymizer implements AnonymizerInterface
{
    private const array LOGS = [
        'logs_not_found',
        'logs_access_denied',
        'logs_rate_limit',
        'logs_security_measure',
        'logs_suspicious_url',
        'logs_incident',
        'command_execution_log',
    ];

    private const array REQUEST_META = ['ip', 'user_agent'];

    public function __construct(
        private Scrubber $scrubber,
        private SendlogInterface $sendlog,
        private BlocklistInterface $blocklist,
        private ActionQueueInterface $actionQueue,
        private HealthInterface $taskHealth,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 30;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            ...array_map(Claim::emptied(...), self::LOGS),
            Claim::columns('activity', rewritten: ['meta'], kept: ['type']),
            Claim::emptied('mod_email_queue'),
            Claim::emptied('mod_email_blocklist'),
            Claim::emptied('mod_task_message'),
            Claim::columns('mod_task_action_request', cleared: ['requested_by'], kept: ['message', 'step_cursor', 'identifier', 'action_values']),
            Claim::columns('mod_task_action_setting', kept: ['identifier', 'setting_key', 'setting_value']),
            Claim::columns('mod_task_run', kept: ['identifier', 'message', 'host']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $rows = 0;
        foreach (self::LOGS as $table) {
            $rows += $this->scrubber->empty($table);
        }
        $rows += $this->sendlog->forgetAll();
        $rows += $this->blocklist->forgetAll();
        $rows += $this->taskHealth->discardFailed();
        $this->actionQueue->forgetRequesters();

        foreach (self::REQUEST_META as $key) {
            $this->scrubber->rewrite('activity', ['meta' => "JSON_SET(meta, :path, '')"], "JSON_CONTAINS_PATH(meta, 'one', :path)", ['path' => '$.' . $key]);
        }
        $this->scrubber->rewrite(
            'activity',
            ['meta' => "JSON_SET(meta, '$.old', CONCAT('member-', user_id), '$.new', CONCAT('member-', user_id))"],
            'type = :type',
            ['type' => ChangedUsername::TYPE],
        );
        $this->scrubber->rewrite(
            'activity',
            ['meta' => "JSON_SET(meta, '$.topic_title', COALESCE((SELECT t.title FROM topic t WHERE t.id = JSON_VALUE(meta, '$.topic_id')), ''))"],
            "JSON_CONTAINS_PATH(meta, 'one', '$.topic_title')",
        );

        $context->report(sprintf('log, mail and failed message rows deleted: %d', $rows));
    }
}
