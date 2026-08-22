<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Support\RecipientResolver;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SupportNotificationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private RecipientResolver $recipientResolver,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportNotification->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_notification';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('New Support Request from %s', $sample->recipientName),
            'context' => [
                'audience' => 'Organizers',
                'name' => $sample->recipientName,
                'email' => 'florence.shaw@example.org',
                'message' => $sample->messageText,
                'createdAt' => $sample->dates->createdAt,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new SupportRequestPresentRule(),
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function send(array $context): void
    {
        /** @var SupportRequest $request */
        $request = $context['request'];

        $recipients = $this->recipientResolver->resolve($request);
        if ($recipients === []) {
            $this->logger->warning('Support ticket received but no recipients could be resolved', [
                'support_request_id' => $request->getId(),
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            $email = new TemplatedEmail();
            $email->from($this->config->getMailerAddress());
            $email->to((string) $recipient->getEmail());
            $email->locale('en');
            $email->context([
                'audience' => $this->translator->trans($request->getAudience()->label(), [], null, 'en'),
                'name' => $request->getRequesterLabel(),
                'email' => $request->getEmail(),
                'message' => $request->getMessage(),
                'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            $this->queue->enqueue($this, $email, $context);
        }
    }
}
