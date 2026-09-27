<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use DateTimeImmutable;
use Module\Email\Contract\DueContext;
use Module\Email\Contract\ScheduledEmailInterface;
use Override;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class ScheduledEmail implements ScheduledEmailInterface
{
    public const string IDENTIFIER = 'module_test_scheduled';

    /** @var list<DueContext> */
    public array $due = [];

    /** @var list<DueContext> */
    public array $marked = [];

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
        return ['subject' => TemplateProvider::SUBJECT, 'context' => ['name' => 'Mock', 'itemsHtml' => '']];
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
     * @param array{user: string, name?: string} $context
     */
    #[Override]
    public function compose(array $context): array
    {
        return [new TemplatedEmail()
            ->from(TriggeredEmail::SENDER)
            ->to($context['user'])
            ->locale('en')
            ->context(['name' => $context['name'] ?? '', 'itemsHtml' => ''])];
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

    #[Override]
    public function getDueContexts(DateTimeImmutable $now): array
    {
        return $this->due;
    }

    #[Override]
    public function getPreviewContexts(DateTimeImmutable $for): array
    {
        return $this->due;
    }

    #[Override]
    public function markContextSent(DueContext $context): void
    {
        $this->marked[] = $context;
    }

    #[Override]
    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }
}
