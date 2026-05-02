<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\SectionsHtmlPresentRule;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class AdminNotificationEmail extends EmailAbstract
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
        return EmailType::AdminNotification->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_admin_notification';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Admin: Items require your attention',
            'context' => [
                'username' => 'Admin User',
                'sections' => '<h3>Users Pending Approval</h3><ul><li>Jane Smith (jane@example.org)</li></ul>',
                'host' => 'https://localhost',
                'lang' => 'en',
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new SectionsHtmlPresentRule(),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        $sectionsHtml = $context['sectionsHtml'];

        $language = $user->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'sections' => $sectionsHtml,
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($this, $email, EmailType::AdminNotification, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT12H'));
    }
}
