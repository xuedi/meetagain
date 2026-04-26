<?php declare(strict_types=1);

namespace App\Service\Member;

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
use Doctrine\ORM\EntityManagerInterface;

readonly class UserService
{
    public const array ALLOWED_ROLES = ['user', 'admin'];
    public const array ALLOWED_FLAGS = ['verified', 'restricted'];
    private const array ALLOWED_STATUS_TRANSITIONS = [
        UserStatus::EmailVerified->value => [UserStatus::Active, UserStatus::Denied],
        UserStatus::Active->value        => [UserStatus::Blocked],
        UserStatus::Blocked->value       => [UserStatus::Active],
        UserStatus::Registered->value    => [UserStatus::Denied],
    ];

    public function __construct(
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private EntityActionDispatcher $dispatcher,
        private ActivityService $activityService,
        private WelcomeEmail $welcomeEmail,
    ) {}

    public function resolveUserName(int $id): string
    {
        return $this->userRepo->resolveUserName($id);
    }

    public function changeRole(User $actor, User $target, UserRole $newRole): void
    {
        $this->assertStructural($actor, $target);

        if ($target->getRole() === $newRole) {
            throw MemberActionException::noOp();
        }

        $target->setRole($newRole);
        $this->em->flush();

        $type = $newRole === UserRole::Admin ? AdminMemberPromoted::TYPE : AdminMemberDemoted::TYPE;
        $this->activityService->log($type, $actor, ['user_id' => $target->getId()]);
    }

    public function toggleVerified(User $actor, User $target): bool
    {
        $this->assertStructural($actor, $target);

        $newValue = !$target->isVerified();
        $target->setVerified($newValue);
        $this->em->flush();

        $type = $newValue ? AdminMemberVerified::TYPE : AdminMemberUnverified::TYPE;
        $this->activityService->log($type, $actor, ['user_id' => $target->getId()]);

        return $newValue;
    }

    public function toggleRestricted(User $actor, User $target): bool
    {
        $this->assertStructural($actor, $target);

        $newValue = !$target->isRestricted();
        $target->setRestricted($newValue);
        $this->em->flush();

        $type = $newValue ? AdminMemberRestricted::TYPE : AdminMemberUnrestricted::TYPE;
        $this->activityService->log($type, $actor, ['user_id' => $target->getId()]);

        return $newValue;
    }

    public function transitionStatus(User $actor, User $target, UserStatus $newStatus): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        if ($oldStatus === $newStatus) {
            throw MemberActionException::noOp();
        }

        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$oldStatus->value] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw MemberActionException::invalidStatusTransition();
        }

        $target->setStatus($newStatus);
        $this->em->flush();

        if ($oldStatus === UserStatus::EmailVerified && $newStatus === UserStatus::Active) {
            $this->welcomeEmail->send(['user' => $target]);
        }

        $type = match (true) {
            $oldStatus === UserStatus::EmailVerified && $newStatus === UserStatus::Active => AdminMemberApproved::TYPE,
            $newStatus === UserStatus::Denied => AdminMemberDenied::TYPE,
            default => AdminMemberStatusChanged::TYPE,
        };

        $meta = ['user_id' => $target->getId()];
        if ($type === AdminMemberStatusChanged::TYPE) {
            $meta['old'] = $oldStatus->value;
            $meta['new'] = $newStatus->value;
        }
        $this->activityService->log($type, $actor, $meta);
    }

    public function softDelete(User $actor, User $target): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        if ($oldStatus === UserStatus::Deleted) {
            throw MemberActionException::noOp();
        }

        $target->setStatus(UserStatus::Deleted);
        $this->em->persist($target);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::DeleteUser, (int) $target->getId());

        $this->activityService->log(AdminMemberStatusChanged::TYPE, $actor, [
            'user_id' => $target->getId(),
            'old' => $oldStatus->value,
            'new' => UserStatus::Deleted->value,
        ]);
    }

    public function restore(User $actor, User $target): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        if ($oldStatus === UserStatus::Active) {
            throw MemberActionException::noOp();
        }

        $target->setStatus(UserStatus::Active);
        $this->em->persist($target);
        $this->em->flush();

        $this->activityService->log(AdminMemberStatusChanged::TYPE, $actor, [
            'user_id' => $target->getId(),
            'old' => $oldStatus->value,
            'new' => UserStatus::Active->value,
        ]);
    }

    private function assertStructural(User $actor, User $target): void
    {
        if ($actor->getId() === $target->getId()) {
            throw MemberActionException::selfModification();
        }

        if ($target->getRole() === UserRole::System) {
            throw MemberActionException::systemUser();
        }
    }
}
