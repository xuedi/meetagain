<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class VerificationRequestEmail extends EmailAbstract
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
        return EmailType::VerificationRequest->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_verification_request';
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Please Confirm your Email',
            'context' => [
                'host' => $sample->host,
                'token' => 'a1b2c3d4e5f60718',
                'username' => $sample->recipientName,
                'url' => $sample->url,
                'lang' => $locale,
            ],
        ];
    }

    public function compose(array $context): array
    {
        /** @var User $user */
        $user = $context['user'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'token' => $user->getRegcode(),
            'username' => $user->getName(),
            'lang' => $user->getLocale(),
        ]);

        return [$email];
    }
}
