<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit;

use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\SettlementListenerInterface;
use Module\Ballot\Internal\SettlementRegistry;
use PHPUnit\Framework\TestCase;

class SettlementRegistryTest extends TestCase
{
    public function testTheHighestPriorityListenerClaimingThePurposeWins(): void
    {
        // Arrange
        $quiet = $this->listener('venue.pick', priority: 0);
        $loud = $this->listener('venue.pick', priority: 10);
        $registry = new SettlementRegistry([$quiet, $loud]);

        // Act
        $chosen = $registry->listenerFor('venue.pick');

        // Assert
        self::assertSame($loud, $chosen);
    }

    public function testAPurposeNobodyClaimsSettlesAndWritesNothing(): void
    {
        // Arrange
        $listener = $this->listener('venue.pick', priority: 0);
        $registry = new SettlementRegistry([$listener]);

        // Act
        $registry->settled($this->outcome('nobody.claims.this'));

        // Assert
        self::assertNull($registry->listenerFor('nobody.claims.this'));
        self::assertSame([], $listener->seen);
    }

    public function testOnlyTheFirstMatchIsNotified(): void
    {
        // Arrange
        $winner = $this->listener('venue.pick', priority: 5);
        $loser = $this->listener('venue.pick', priority: 1);
        $registry = new SettlementRegistry([$loser, $winner]);

        // Act
        $registry->settled($this->outcome('venue.pick'));

        // Assert
        self::assertCount(1, $winner->seen);
        self::assertSame([], $loser->seen);
    }

    private function outcome(string $purpose): BallotOutcome
    {
        return new BallotOutcome(1, $purpose, BallotStatus::Settled, 'a');
    }

    private function listener(string $purpose, int $priority): SettlementListenerInterface
    {
        return new class($purpose, $priority) implements SettlementListenerInterface {
            /** @var list<BallotOutcome> */
            public array $seen = [];

            public function __construct(
                private readonly string $purpose,
                private readonly int $priority,
            ) {}

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function supports(string $purpose): bool
            {
                return $purpose === $this->purpose;
            }

            public function settled(BallotOutcome $outcome): void
            {
                $this->seen[] = $outcome;
            }
        };
    }
}
