<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\SectionsHtmlPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use DateInterval;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class AdminNotificationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistInterface $blocklist,
        MockSampleFactory $samples,
        MailerInterface $mailer,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples, $mailer);
    }

    public function getIdentifier(): string
    {
        return EmailType::AdminNotification->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_admin_notification';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Admin: Items require your attention',
            'context' => [
                'username' => $sample->adminName,
                'sections' => $this->samples->sectionsHtml($locale),
                'host' => $sample->host,
                'lang' => $locale,
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

    public function compose(array $context): array
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
            'lang' => $language,
        ]);

        return [$email];
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT12H'));
    }
}
