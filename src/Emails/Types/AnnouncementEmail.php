<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\RenderedAnnouncementPresentRule;
use App\Emails\MockSampleFactory;
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
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::Announcement->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_announcement';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => $sample->announcementTitle,
            'context' => [
                'title' => $sample->announcementTitle,
                'content' => $sample->announcementBody,
                'announcementUrl' => $sample->host . '/announcement/9f4c1ab7d20e5361',
                'username' => $sample->recipientName,
                'host' => $sample->host,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new RenderedAnnouncementPresentRule(),
            new NotificationToggleEnabledRule('announcements'),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
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
            'lang' => $locale,
        ]);

        $this->queue->enqueue($this, $email, $context, false);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT24H'));
    }
}
