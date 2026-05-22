<?php declare(strict_types=1);

namespace App\Tests\Unit\Security\Permission;

use App\Entity\User;
use App\Security\Permission\PermissionCheckerInterface;
use App\Security\Permission\PermissionContext;
use App\Security\Permission\PermissionGateway;
use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[AllowMockObjectsWithoutExpectations]
class PermissionGatewayTest extends TestCase
{
    public function testAbstainsWithNoCheckers(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false);

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenNoCheckerSupportsAttribute(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: false, decision: true));

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testGrantsWhenSupportingCheckerVotesTrue(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: true, decision: true));

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesWhenSupportingCheckerVotesFalse(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: true, decision: false));

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testFirstGrantWinsOverEarlierAbstain(): void
    {
        // Arrange
        $gateway = $this->makeGateway(
            false,
            $this->fixedChecker(supports: true, decision: null),
            $this->fixedChecker(supports: true, decision: true),
        );

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantBeatsDenyAcrossCheckers(): void
    {
        // Arrange - if any supporting checker grants, gateway grants
        $gateway = $this->makeGateway(
            false,
            $this->fixedChecker(supports: true, decision: false),
            $this->fixedChecker(supports: true, decision: true),
        );

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAbstainWhenSupportingCheckersAllAbstain(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: true, decision: null));

        // Act
        $result = $gateway->vote($this->makeToken(), null, ['anything']);

        // Assert
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testContextCarriesActorAndAdminFlag(): void
    {
        // Arrange
        $user = new User();
        $captured = null;
        $checker = new class($captured) implements PermissionCheckerInterface {
            public function __construct(
                public ?PermissionContext &$captured,
            ) {}

            #[Override]
            public function supports(string $attribute, mixed $subject): bool
            {
                return true;
            }

            #[Override]
            public function vote(string $attribute, PermissionContext $context): ?bool
            {
                $this->captured = $context;

                return true;
            }
        };
        $gateway = $this->makeGateway(true, $checker);

        // Act
        $gateway->vote($this->makeToken($user), 'subject-payload', ['anything']);

        // Assert
        self::assertNotNull($captured);
        self::assertSame($user, $captured->actor);
        self::assertSame('subject-payload', $captured->subject);
        self::assertTrue($captured->isAdmin);
    }

    public function testNonStringAttributesAreSkipped(): void
    {
        // Arrange - a checker that would grant for strings; with non-string attrs it should never be consulted
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: true, decision: true));

        // Act
        $result = $gateway->vote($this->makeToken(), null, [42, ['nested']]);

        // Assert
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsAttributeReturnsTrueWhenAnyCheckerSupports(): void
    {
        // Arrange
        $gateway = $this->makeGateway(
            false,
            $this->fixedChecker(supports: false, decision: null),
            $this->fixedChecker(supports: true, decision: null),
        );

        // Act / Assert
        self::assertTrue($gateway->supportsAttribute('anything'));
    }

    public function testSupportsAttributeReturnsFalseWhenNoCheckerSupports(): void
    {
        // Arrange
        $gateway = $this->makeGateway(false, $this->fixedChecker(supports: false, decision: null));

        // Act / Assert
        self::assertFalse($gateway->supportsAttribute('anything'));
    }

    public function testSupportsTypeIsAlwaysTrue(): void
    {
        $gateway = $this->makeGateway(false);

        self::assertTrue($gateway->supportsType('anything'));
        self::assertTrue($gateway->supportsType(User::class));
    }

    public function testContextActorIsNullWhenTokenHasNoUser(): void
    {
        // Arrange
        $captured = null;
        $checker = new class($captured) implements PermissionCheckerInterface {
            public function __construct(
                public ?PermissionContext &$captured,
            ) {}

            #[Override]
            public function supports(string $attribute, mixed $subject): bool
            {
                return true;
            }

            #[Override]
            public function vote(string $attribute, PermissionContext $context): ?bool
            {
                $this->captured = $context;

                return true;
            }
        };
        $gateway = $this->makeGateway(false, $checker);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        // Act
        $gateway->vote($token, null, ['anything']);

        // Assert
        self::assertNotNull($captured);
        self::assertNull($captured->actor);
        self::assertFalse($captured->isAdmin);
    }

    private function makeGateway(bool $isAdmin, PermissionCheckerInterface ...$checkers): PermissionGateway
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        return new PermissionGateway($security, $checkers);
    }

    private function makeToken(?User $user = null): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function fixedChecker(bool $supports, ?bool $decision): PermissionCheckerInterface
    {
        return new class($supports, $decision) implements PermissionCheckerInterface {
            public function __construct(
                private readonly bool $supportsResult,
                private readonly ?bool $decision,
            ) {}

            #[Override]
            public function supports(string $attribute, mixed $subject): bool
            {
                return $this->supportsResult;
            }

            #[Override]
            public function vote(string $attribute, PermissionContext $context): ?bool
            {
                return $this->decision;
            }
        };
    }
}
