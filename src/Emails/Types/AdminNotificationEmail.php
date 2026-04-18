<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class AdminNotificationEmail implements EmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::AdminNotification->value;
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

    public function guardCheck(array $context): bool
    {
        return true;
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

        $this->queue->enqueue($email, EmailType::AdminNotification);
    }
}
