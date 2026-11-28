<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Override;

readonly class MemberContentAnonymizer implements AnonymizerInterface
{
    public function __construct(
        private Scrubber $scrubber,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 20;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('comment', rewritten: ['content'], kept: ['target_type']),
            Claim::columns('message', rewritten: ['content']),
            Claim::columns('topic', rewritten: ['title']),
            Claim::columns('host', rewritten: ['name']),
            Claim::columns('support_request', rewritten: ['email', 'message'], cleared: ['ip_address'], kept: ['token', 'email_verify_token', 'locale']),
            Claim::columns('support_message', rewritten: ['content'], cleared: ['ip_address']),
            Claim::columns('support_request_view'),
            Claim::columns('moderation_report', rewritten: ['subject_label', 'excerpt', 'remarks', 'resolution_note'], kept: ['subject_type']),
            Claim::columns('item_report', rewritten: ['explanation', 'notifier_name', 'notifier_email'], kept: ['item_type', 'item_label', 'locale']),
            Claim::columns('image_report', rewritten: ['remarks']),
            Claim::columns('mod_proposal_change', rewritten: ['reason'], kept: ['target_type', 'changes', 'requested_scope']),
            Claim::emptied('account_deletion_feedback'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $texts = $this->scrubber->fill('comment', 'content');
        $texts += $this->scrubber->fill('message', 'content');
        $texts += $this->scrubber->fill('topic', 'title');
        $texts += $this->scrubber->fill('support_request', 'message');
        $texts += $this->scrubber->fill('support_message', 'content');
        $texts += $this->scrubber->fill('moderation_report', 'excerpt', 'remarks', 'resolution_note');
        $texts += $this->scrubber->fill('item_report', 'explanation');
        $texts += $this->scrubber->fill('image_report', 'remarks');
        $texts += $this->scrubber->fill('mod_proposal_change', 'reason');

        $this->scrubber->rewrite('host', ['name' => "CONCAT('Host ', id)"]);
        $this->scrubber->rewrite('support_request', ['email' => $this->scrubber->address('support')], 'email IS NOT NULL');
        $this->scrubber->clear('support_request', 'ip_address');
        $this->scrubber->clear('support_message', 'ip_address');
        $this->scrubber->rewrite('moderation_report', ['subject_label' => "CONCAT(subject_type, ' ', subject_id)"]);
        $this->scrubber->rewrite('item_report', [
            'notifier_name' => "CONCAT('Notifier ', id)",
            'notifier_email' => $this->scrubber->address('report'),
        ]);

        $feedback = $this->scrubber->empty('account_deletion_feedback');

        $context->report(sprintf('texts: %d, deletion feedback: %d', $texts, $feedback));
    }
}
