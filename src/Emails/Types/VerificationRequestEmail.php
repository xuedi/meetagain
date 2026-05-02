<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class VerificationRequestEmail extends EmailAbstract
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

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Please Confirm your Email',
            'context' => [
                'host' => 'https://localhost/en',
                'token' => '1234567890',
                'username' => 'John Doe',
                'url' => 'https://localhost/en',
                'lang' => 'en',
            ],
        ];
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'host' => $this->config->getHost(),
            'token' => $user->getRegcode(),
            'url' => $this->config->getUrl(),
            'username' => $user->getName(),
            'lang' => $user->getLocale(),
        ]);

        $this->queue->enqueue($this, $email, EmailType::VerificationRequest, $context);
    }
}
