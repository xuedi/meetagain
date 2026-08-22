<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\MockSampleFactory;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportEmailVerifyEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportEmailVerify->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_email_verify';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Confirm your email address',
            'context' => [
                'host' => $sample->host,
                'url' => $sample->url,
                'lang' => $locale,
                'token' => str_repeat('0', 64),
                'expiresAt' => $sample->dates->expiresAt,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function send(array $context): void
    {
        $recipient = (string) $context['email'];
        $token = (string) $context['token'];
        /** @var DateTimeImmutable $expiresAt */
        $expiresAt = $context['expiresAt'];

        if ($this->blocklist->isBlocked($recipient)) {
            return;
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($recipient);
        $email->locale((string) ($context['lang'] ?? 'en'));
        $email->context([
            'lang' => (string) ($context['lang'] ?? 'en'),
            'token' => $token,
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        $this->queue->enqueue($this, $email, $context);
    }
}
