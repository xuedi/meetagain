<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class NotificationMessageEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::NotificationMessage->value;
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

    public function guardCheck(array $context): bool
    {
        $this->ensureInstanceOf($context, 'recipient', User::class);
        $this->ensureInstanceOf($context, 'sender', User::class);

        /** @var User $recipient */
        $recipient = $context['recipient'];

        if ($this->isBlocked((string) $recipient->getEmail())) {
            return false;
        }
        if (!$recipient->isNotification()) {
            return false;
        }
        if (!$recipient->getNotificationSettings()->receivedMessage) {
            return false;
        }
        if ($recipient->getLastLogin() > new DateTime('-2 hours')) {
            return false;
        }

        return true;
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
