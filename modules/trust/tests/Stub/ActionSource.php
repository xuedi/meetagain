<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use DateTimeImmutable;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\TrustAction;
use Override;

final readonly class ActionSource implements ActionSourceInterface
{
    public const string HANDOVER = 'stub_handover';
    public const string TENURE = 'stub_tenure';
    public const int HANDOVERS = 4;
    public const int DEFAULT_POINTS = 5;
    public const int TENURE_MONTHS = 40;
    public const int TENURE_CAP = 24;

    public function __construct(
        private UserLocator $locator,
    ) {}

    #[Override]
    public function describeActions(string $context): iterable
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return;
        }

        yield new ActionDescriptor(self::HANDOVER, 'trust_stub.action_handover', self::DEFAULT_POINTS);
        yield new ActionDescriptor(self::TENURE, 'trust_stub.action_tenure', 1, self::TENURE_CAP);
    }

    #[Override]
    public function replay(string $context): iterable
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return;
        }

        $earnerId = $this->locator->idFor(UserLocator::EARNER_EMAIL);
        if ($earnerId === null) {
            return;
        }

        for ($i = 0; $i < self::HANDOVERS; $i++) {
            yield new TrustAction($earnerId, self::HANDOVER, new DateTimeImmutable('2026-01-0' . ($i + 1)));
        }

        yield new TrustAction($earnerId, self::TENURE, new DateTimeImmutable('2026-01-01'), self::TENURE_MONTHS);
        yield new TrustAction($earnerId, 'stub_never_declared', new DateTimeImmutable('2026-01-01'));
    }

    #[Override]
    public function getRevision(string $context): ?string
    {
        return $context === ContextDescriber::CONTEXT ? 'stub-' . self::HANDOVERS : null;
    }
}
