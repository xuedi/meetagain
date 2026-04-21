<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
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

    public function guardCheck(array $context): bool
    {
        $this->ensureInstanceOf($context, 'user', User::class);

        /** @var User $user */
        $user = $context['user'];
        if ($this->isBlocked((string) $user->getEmail())) {
            return false;
        }

        return true;
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
