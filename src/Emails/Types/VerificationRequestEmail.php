<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class VerificationRequestEmail implements EmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {}

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

        $this->queue->enqueue($email, EmailType::VerificationRequest);
    }
}
