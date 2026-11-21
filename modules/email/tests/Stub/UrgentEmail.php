<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use DateTimeImmutable;
use Module\Email\Contract\UrgentEmailInterface;
use Override;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class UrgentEmail implements UrgentEmailInterface
{
    public const string IDENTIFIER = 'module_test_urgent';

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getTriggerLabel(): string
    {
        return 'module_test.trigger';
    }

    #[Override]
    public function getDisplayMockData(string $locale): array
    {
        return ['subject' => TemplateProvider::SUBJECT, 'context' => ['name' => 'Mock', 'itemsHtml' => '<li>mock</li>']];
    }

    #[Override]
    public function getOrigin(array $context): ?object
    {
        return null;
    }

    #[Override]
    public function getGuardRules(): array
    {
        return [];
    }

    /**
     * @param array{recipients?: list<string>} $context
     */
    #[Override]
    public function compose(array $context): array
    {
        return array_map(static fn(string $recipient): TemplatedEmail => new TemplatedEmail()
            ->from(TriggeredEmail::SENDER)
            ->to($recipient)
            ->locale('en')
            ->context(['name' => '', 'itemsHtml' => '']), $context['recipients'] ?? []);
    }

    #[Override]
    public function pushOnEnqueue(): bool
    {
        return false;
    }

    #[Override]
    public function getAttachments(array $context): array
    {
        return [];
    }

    #[Override]
    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return null;
    }
}
