<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class AnnouncementEmail extends EmailAbstract
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
        return EmailType::Announcement->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_announcement';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Important Community Update',
            'context' => [
                'title' => 'Important Community Update',
                'content' => '<p>We are excited to announce some upcoming changes to our community platform.</p><p>Stay tuned for more details!</p>',
                'announcementUrl' => 'https://localhost/announcement/abc123def456',
                'username' => 'John Doe',
                'host' => 'https://localhost',
                'lang' => 'en',
            ],
        ];
    }

    public function guardCheck(array $context): bool
    {
        $this->ensureInstanceOf($context, 'user', User::class);
        $this->ensureHasKey($context, 'renderedContent');
        $this->ensureHasKey($context, 'announcementUrl');

        /** @var User $user */
        $user = $context['user'];
        if ($this->isBlocked((string) $user->getEmail())) {
            return false;
        }

        return $user->getNotificationSettings()->isActive('announcements');
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        $renderedContent = $context['renderedContent'];
        $announcementUrl = $context['announcementUrl'];

        $locale = $user->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($locale);
        $email->context([
            'title' => $renderedContent['title'],
            'content' => $renderedContent['content'],
            'announcementUrl' => $announcementUrl,
            'username' => $user->getName(),
            'host' => $this->config->getHost(),
            'lang' => $locale,
        ]);

        $this->queue->enqueue($this, $email, EmailType::Announcement, $context, false);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT24H'));
    }
}
