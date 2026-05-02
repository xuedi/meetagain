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
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private ClockInterface $clock,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::NotificationMessage->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_notification_message';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'You received a message from %senderName%',
            'context' => [
                'username' => 'John Doe',
                'sender' => 'john.doe@example.org',
                'senderId' => 1,
                'host' => 'https://localhost/en',
                'lang' => 'en',
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
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($this, $email, EmailType::NotificationMessage, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT6H'));
    }
}
