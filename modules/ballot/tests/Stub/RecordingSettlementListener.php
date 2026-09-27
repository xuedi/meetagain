<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Stub;

use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\SettlementListenerInterface;
use Override;

class RecordingSettlementListener implements SettlementListenerInterface
{
    public const string PURPOSE = 'test.claimed';

    /** @var list<BallotOutcome> */
    public array $settled = [];

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === self::PURPOSE;
    }

    #[Override]
    public function settled(BallotOutcome $outcome): void
    {
        $this->settled[] = $outcome;
    }
}
