<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit;

use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\ElectorateProviderInterface;
use Module\Ballot\Internal\ElectorateRegistry;
use PHPUnit\Framework\TestCase;

class ElectorateRegistryTest extends TestCase
{
    public function testWithNoProviderEveryAuthenticatedMemberMayVote(): void
    {
        // Arrange
        $registry = new ElectorateRegistry([]);

        // Act
        $verdict = $registry->mayVote('anything', 7, null);

        // Assert
        self::assertTrue($verdict);
    }

    public function testAnAnonymousVisitorNeverVotes(): void
    {
        // Arrange
        $registry = new ElectorateRegistry([$this->provider('anything', allows: true)]);

        // Act
        $verdict = $registry->mayVote('anything', 0, null);

        // Assert
        self::assertFalse($verdict);
    }

    public function testTheFirstProviderClaimingThePurposeAnswersForIt(): void
    {
        // Arrange
        $registry = new ElectorateRegistry([
            $this->provider('venue.pick', allows: false),
            $this->provider('venue.pick', allows: true),
        ]);

        // Act
        $verdict = $registry->mayVote('venue.pick', 7, new BallotSubject('event', 1));

        // Assert
        self::assertFalse($verdict, 'the second provider never gets asked');
    }

    public function testAProviderIsIgnoredForAPurposeItDoesNotClaim(): void
    {
        // Arrange
        $registry = new ElectorateRegistry([$this->provider('venue.pick', allows: false)]);

        // Act
        $verdict = $registry->mayVote('something.else', 7, null);

        // Assert
        self::assertTrue($verdict);
    }

    private function provider(string $purpose, bool $allows): ElectorateProviderInterface
    {
        return new class($purpose, $allows) implements ElectorateProviderInterface {
            public function __construct(
                private readonly string $purpose,
                private readonly bool $allows,
            ) {}

            public function supports(string $purpose): bool
            {
                return $purpose === $this->purpose;
            }

            public function mayVote(int $userId, ?BallotSubject $subject): bool
            {
                return $this->allows;
            }
        };
    }
}
