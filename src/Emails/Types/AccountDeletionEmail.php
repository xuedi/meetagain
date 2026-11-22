<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Member\Erasure;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\UrgentEmailInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class AccountDeletionEmail extends EmailAbstract implements UrgentEmailInterface
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
        return EmailType::AccountDeletion->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_account_deletion';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Your account was deleted',
            'context' => [
                'username' => $sample->recipientName,
                'erasureDate' => $sample->dates->erasureDate,
                'host' => $sample->host,
                'url' => $sample->url,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function compose(array $context): array
    {
        /** @var User $user */
        $user = $context['user'];
        $requestedAt = $user->getDeletionRequestedAt();
        if (!$requestedAt instanceof DateTimeImmutable) {
            return [];
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'username' => $user->getName(),
            'erasureDate' => $requestedAt->modify(sprintf('+%d days', Erasure::ERASURE_WINDOW_DAYS))->format('Y-m-d'),
            'lang' => $user->getLocale(),
        ]);

        return [$email];
    }
}
