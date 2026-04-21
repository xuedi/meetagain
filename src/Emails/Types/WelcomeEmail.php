<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class WelcomeEmail extends EmailAbstract
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::Welcome->value;
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Welcome!',
            'context' => [
                'host' => 'https://localhost/en',
                'url' => 'https://localhost/en',
                'lang' => 'en',
            ],
        ];
    }

    public function guardCheck(array $context): bool
    {
        $this->ensureInstanceOf($context, 'user', User::class);

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
            'url' => $this->config->getUrl(),
            'host' => $this->config->getHost(),
            'lang' => $user->getLocale(),
        ]);

        $this->queue->enqueue($this, $email, EmailType::Welcome, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT12H'));
    }
}
