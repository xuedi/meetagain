<?php declare(strict_types=1);

namespace Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Security\Voter\DashboardVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DashboardVoterTest extends TestCase
{
    private DashboardVoter $voter;

    protected function setUp(): void
    {
        // Arrange: Create voter without Multisite plugin
        $this->voter = new DashboardVoter(null);
    }

    public function testRoleAdminAlwaysGranted(): void
    {
        // Arrange: Create admin user
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Act: Vote on dashboard access
        $result = $this->voter->vote($token, null, [DashboardVoter::ACCESS]);

        // Assert: Admin is granted access
        $this->assertSame(1, $result, 'ROLE_ADMIN should be granted dashboard access');
    }

    public function testRegularUserDenied(): void
    {
        // Arrange: Create regular user without admin role
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Act: Vote on dashboard access
        $result = $this->voter->vote($token, null, [DashboardVoter::ACCESS]);

        // Assert: Regular user is denied (returns -1)
        $this->assertSame(-1, $result, 'Regular user should be denied dashboard access');
    }

    public function testUnauthenticatedUserDenied(): void
    {
        // Arrange: Create token without user
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        // Act: Vote on dashboard access
        $result = $this->voter->vote($token, null, [DashboardVoter::ACCESS]);

        // Assert: Unauthenticated user is denied
        $this->assertSame(-1, $result, 'Unauthenticated user should be denied dashboard access');
    }

    public function testGroupOwnerGrantedWithMultisite(): void
    {
        // Arrange: Create group context service mock
        $groupContextService = new class {
            public function getManagedGroupsForUser(User $user): array
            {
                return [new \stdClass()]; // User manages at least one group
            }
        };

        $voter = new DashboardVoter($groupContextService);

        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Act: Vote on dashboard access
        $result = $voter->vote($token, null, [DashboardVoter::ACCESS]);

        // Assert: Group owner is granted access
        $this->assertSame(1, $result, 'Group owner should be granted dashboard access');
    }

    public function testSupportsOnlyDashboardAccess(): void
    {
        // Arrange: Test different attributes
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Act & Assert: Only supports DASHBOARD_ACCESS
        $result = $this->voter->vote($token, null, [DashboardVoter::ACCESS]);
        $this->assertNotSame(0, $result, 'Should support DASHBOARD_ACCESS attribute');

        $result = $this->voter->vote($token, null, ['SOME_OTHER_ATTRIBUTE']);
        $this->assertSame(0, $result, 'Should abstain from voting on other attributes');
    }
}
