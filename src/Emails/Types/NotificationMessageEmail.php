<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientKeyUserPresentRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientNotRecentlyActiveRule;
use App\Emails\Guard\Rule\SenderUserPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;

readonly class NotificationMessageEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private ClockInterface $clock,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::NotificationMessage->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_notification_message';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('You received a message from %s', $sample->senderName),
            'context' => [
                'username' => $sample->recipientName,
                'sender' => $sample->senderName,
                'senderId' => 41,
                'host' => $sample->host,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientKeyUserPresentRule(),
            new SenderUserPresentRule(),
            new UserNotificationsMasterToggleRule('recipient'),
            new NotificationToggleEnabledRule('receivedMessage', 'recipient'),
            new RecipientNotRecentlyActiveRule($this->clock, new DateInterval('PT2H'), 'recipient'),
            new RecipientNotBlocklistedRule($this->blocklist, 'recipient'),
        ];
    }

    public function getOrigin(array $context): ?object
    {
        $recipient = $context['recipient'] ?? null;

        return $recipient instanceof User ? $recipient : null;
    }

    public function send(array $context): void
    {
        /** @var User $sender */
        $sender = $context['sender'];
        /** @var User $recipient */
        $recipient = $context['recipient'];

        $language = $recipient->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'sender' => $sender->getName(),
            'senderId' => $sender->getId(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($this, $email, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT6H'));
    }
}
