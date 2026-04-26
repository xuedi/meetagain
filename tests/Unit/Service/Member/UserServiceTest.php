<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminMemberApproved;
use App\Activity\Messages\AdminMemberDemoted;
use App\Activity\Messages\AdminMemberDenied;
use App\Activity\Messages\AdminMemberPromoted;
use App\Activity\Messages\AdminMemberRestricted;
use App\Activity\Messages\AdminMemberStatusChanged;
use App\Activity\Messages\AdminMemberUnrestricted;
use App\Activity\Messages\AdminMemberUnverified;
use App\Activity\Messages\AdminMemberVerified;
use App\Emails\Types\WelcomeEmail;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Member\MemberActionException;
use App\Service\Member\MemberActionFailure;
use App\Service\Member\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class UserServiceTest extends TestCase
{
    private const int ACTOR_ID = 1;
    private const int TARGET_ID = 2;

    public function testChangeRolePromotesToAdmin(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberPromoted::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $target->expects($this->once())->method('setRole')->with(UserRole::Admin);

        $subject = $this->makeService(em: $em, activityService: $activity);

        // Act
        $subject->changeRole($actor, $target, UserRole::Admin);
    }

    public function testChangeRoleDemotesToUser(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::Admin);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberDemoted::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $subject->changeRole($actor, $target, UserRole::User);
    }

    public function testChangeRoleNoOpWhenSameRole(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::Admin);

        $subject = $this->makeService();

        // Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->changeRole($actor, $target, UserRole::Admin);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::NoOp, $e->failure);
            throw $e;
        }
    }

    public function testChangeRoleSelfModificationThrows(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);

        $subject = $this->makeService();

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->changeRole($actor, $actor, UserRole::User);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::SelfModification, $e->failure);
            throw $e;
        }
    }

    public function testChangeRoleSystemUserThrows(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::System);

        $subject = $this->makeService();

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->changeRole($actor, $target, UserRole::User);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::SystemUser, $e->failure);
            throw $e;
        }
    }

    public function testToggleVerifiedFlipsToTrue(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User);
        $target->method('isVerified')->willReturn(false);
        $target->expects($this->once())->method('setVerified')->with(true);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberVerified::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $result = $subject->toggleVerified($actor, $target);

        // Assert
        self::assertTrue($result);
    }

    public function testToggleVerifiedFlipsToFalse(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User);
        $target->method('isVerified')->willReturn(true);
        $target->expects($this->once())->method('setVerified')->with(false);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberUnverified::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $result = $subject->toggleVerified($actor, $target);

        // Assert
        self::assertFalse($result);
    }

    public function testToggleRestrictedFlipsToTrue(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User);
        $target->method('isRestricted')->willReturn(false);
        $target->expects($this->once())->method('setRestricted')->with(true);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberRestricted::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $result = $subject->toggleRestricted($actor, $target);

        // Assert
        self::assertTrue($result);
    }

    public function testToggleRestrictedFlipsToFalse(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User);
        $target->method('isRestricted')->willReturn(true);
        $target->expects($this->once())->method('setRestricted')->with(false);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberUnrestricted::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $result = $subject->toggleRestricted($actor, $target);

        // Assert
        self::assertFalse($result);
    }

    public function testTransitionStatusEmailVerifiedToActiveSendsWelcomeEmail(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::EmailVerified);
        $target->expects($this->once())->method('setStatus')->with(UserStatus::Active);

        $welcomeEmail = $this->createMock(WelcomeEmail::class);
        $welcomeEmail->expects($this->once())
            ->method('send')
            ->with(['user' => $target]);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberApproved::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(welcomeEmail: $welcomeEmail, activityService: $activity);

        // Act
        $subject->transitionStatus($actor, $target, UserStatus::Active);
    }

    public function testTransitionStatusToDeniedLogsDenied(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::EmailVerified);

        $welcomeEmail = $this->createMock(WelcomeEmail::class);
        $welcomeEmail->expects($this->never())->method('send');

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberDenied::TYPE, $actor, ['user_id' => self::TARGET_ID]);

        $subject = $this->makeService(welcomeEmail: $welcomeEmail, activityService: $activity);

        // Act
        $subject->transitionStatus($actor, $target, UserStatus::Denied);
    }

    public function testTransitionStatusBlockUnblockLogsStatusChanged(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Active);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberStatusChanged::TYPE, $actor, [
                'user_id' => self::TARGET_ID,
                'old' => UserStatus::Active->value,
                'new' => UserStatus::Blocked->value,
            ]);

        $subject = $this->makeService(activityService: $activity);

        // Act
        $subject->transitionStatus($actor, $target, UserStatus::Blocked);
    }

    public function testTransitionStatusInvalidTransitionThrows(): void
    {
        // Arrange: Active -> Denied is not in the allow-list
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Active);

        $subject = $this->makeService();

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->transitionStatus($actor, $target, UserStatus::Denied);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::InvalidStatusTransition, $e->failure);
            throw $e;
        }
    }

    public function testTransitionStatusNoOpWhenSameStatus(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Active);

        $subject = $this->makeService();

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->transitionStatus($actor, $target, UserStatus::Active);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::NoOp, $e->failure);
            throw $e;
        }
    }

    public function testSoftDeleteSetsStatusAndDispatches(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Active);
        $target->expects($this->once())->method('setStatus')->with(UserStatus::Deleted);

        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(EntityAction::DeleteUser, self::TARGET_ID);

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberStatusChanged::TYPE, $actor, [
                'user_id' => self::TARGET_ID,
                'old' => UserStatus::Active->value,
                'new' => UserStatus::Deleted->value,
            ]);

        $subject = $this->makeService(dispatcher: $dispatcher, activityService: $activity);

        // Act
        $subject->softDelete($actor, $target);
    }

    public function testSoftDeleteNoOpWhenAlreadyDeleted(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Deleted);

        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $subject = $this->makeService(dispatcher: $dispatcher);

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->softDelete($actor, $target);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::NoOp, $e->failure);
            throw $e;
        }
    }

    public function testRestoreSetsStatusToActive(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Deleted);
        $target->expects($this->once())->method('setStatus')->with(UserStatus::Active);

        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $activity = $this->createMock(ActivityService::class);
        $activity->expects($this->once())
            ->method('log')
            ->with(AdminMemberStatusChanged::TYPE, $actor, [
                'user_id' => self::TARGET_ID,
                'old' => UserStatus::Deleted->value,
                'new' => UserStatus::Active->value,
            ]);

        $subject = $this->makeService(dispatcher: $dispatcher, activityService: $activity);

        // Act
        $subject->restore($actor, $target);
    }

    public function testRestoreNoOpWhenAlreadyActive(): void
    {
        // Arrange
        $actor = $this->makeUser(self::ACTOR_ID, UserRole::Admin);
        $target = $this->makeUser(self::TARGET_ID, UserRole::User, UserStatus::Active);

        $subject = $this->makeService();

        // Act + Assert
        $this->expectException(MemberActionException::class);
        try {
            $subject->restore($actor, $target);
        } catch (MemberActionException $e) {
            self::assertSame(MemberActionFailure::NoOp, $e->failure);
            throw $e;
        }
    }

    private function makeUser(int $id, UserRole $role, UserStatus $status = UserStatus::Active): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRole')->willReturn($role);
        $user->method('getStatus')->willReturn($status);

        return $user;
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?EntityActionDispatcher $dispatcher = null,
        ?ActivityService $activityService = null,
        ?WelcomeEmail $welcomeEmail = null,
    ): UserService {
        return new UserService(
            userRepo: $this->createStub(UserRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            dispatcher: $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
            activityService: $activityService ?? $this->createStub(ActivityService::class),
            welcomeEmail: $welcomeEmail ?? $this->createStub(WelcomeEmail::class),
        );
    }
}
