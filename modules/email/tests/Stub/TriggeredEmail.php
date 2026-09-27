<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use DateTimeImmutable;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardRuleInterface;
use Override;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class TriggeredEmail implements EmailInterface
{
    public const string IDENTIFIER = 'module_test_triggered';
    public const string SENDER = 'sender@module-test.example';

    /** @var list<GuardRuleInterface> */
    public array $rules = [];

    public bool $push = true;

    public ?DateTimeImmutable $maxSendBy = null;

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
        return $context['origin'] ?? null;
    }

    #[Override]
    public function getGuardRules(): array
    {
        return $this->rules;
    }

    /**
     * @param array{recipients?: list<string>, name?: string, itemsHtml?: string} $context
     */
    #[Override]
    public function compose(array $context): array
    {
        return array_map(static fn(string $recipient): TemplatedEmail => new TemplatedEmail()
            ->from(self::SENDER)
            ->to($recipient)
            ->locale('en')
            ->context(['name' => $context['name'] ?? '', 'itemsHtml' => $context['itemsHtml'] ?? '']), $context['recipients'] ?? []);
    }

    #[Override]
    public function pushOnEnqueue(): bool
    {
        return $this->push;
    }

    #[Override]
    public function getAttachments(array $context): array
    {
        return [];
    }

    #[Override]
    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $this->maxSendBy;
    }
}
