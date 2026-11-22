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
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserService
{
    public const array ALLOWED_ROLES = ['user', 'admin'];
    public const array ALLOWED_FLAGS = ['verified', 'restricted'];
    private const array ALLOWED_STATUS_TRANSITIONS = [
        UserStatus::EmailVerified->value => [UserStatus::Active, UserStatus::Denied],
        UserStatus::Active->value => [UserStatus::Blocked],
        UserStatus::Blocked->value => [UserStatus::Active],
        UserStatus::Registered->value => [UserStatus::Denied],
    ];

    /**
     * @param iterable<DeletionBlockerInterface> $deletionBlockers
     */
    public function __construct(
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private EntityActionDispatcher $dispatcher,
        private ActivityService $activityService,
        private WelcomeEmail $welcomeEmail,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        #[AutowireIterator(DeletionBlockerInterface::class)]
        private iterable $deletionBlockers,
    ) {}

    public function claimRegistrationCode(User $user, #[SensitiveParameter] string $code): bool
    {
        return $this->userRepo->consumeRegcode($user, $code);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userRepo->findOneByEmailIgnoreCase($email);
    }

    public function findUser(int $id): ?User
    {
        return $this->userRepo->find($id);
    }

    /**
     * @return list<User>
     */
    public function findUnconfirmedRegistrations(): array
    {
        return $this->userRepo->findUnconfirmedRegistrations();
    }

    /**
     * @param array<int> $ids
     * @return array<User>
     */
    public function findByIds(array $ids): array
    {
        return $this->userRepo->findBy(['id' => $ids]);
    }

    /**
     * @param array<int>|null $restrictToUserIds
     */
    public function findAllForAdmin(?array $restrictToUserIds = null): array
    {
        return $this->userRepo->findAllForAdmin($restrictToUserIds);
    }

    /**
     * @param int[] $excludeIds
     * @param array<int>|null $restrictToUserIds
     */
    public function countDirectoryMembers(bool $publicOnly, array $excludeIds, ?array $restrictToUserIds): int
    {
        return $publicOnly
            ? $this->userRepo->getNumberOfActivePublicMembers($restrictToUserIds)
            : $this->userRepo->getNumberOfActiveMembers($excludeIds, $restrictToUserIds);
    }

    /**
     * @param int[] $excludeIds
     * @param array<int>|null $restrictToUserIds
     */
    public function findDirectoryMembers(bool $publicOnly, int $limit, int $offset, array $excludeIds, ?array $restrictToUserIds): array
    {
        return $publicOnly
            ? $this->userRepo->findActivePublicMembers($limit, $offset, $restrictToUserIds)
            : $this->userRepo->findActiveMembers($limit, $offset, $excludeIds, $restrictToUserIds);
    }

    public function resolveUserName(int $id): string
    {
        return $this->userRepo->resolveUserName($id) ?? $this->translator->trans('shared.deleted_member');
    }

    public function displayName(?User $user): string
    {
        $name = $user?->getName();
        if ($name === null || $name === '' || $user?->isDeleted() === true) {
            return $this->translator->trans('shared.deleted_member');
        }

        return $name;
    }

    public function changeRole(User $actor, User $target, UserRole $newRole): void
    {
        $this->assertStructural($actor, $target);

        if ($target->getRole() === $newRole) {
            throw ActionException::noOp();
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

    public function transitionStatus(User $actor, User $target, UserStatus $newStatus, ?DateTimeImmutable $blockedUntil = null): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        if ($oldStatus === $newStatus) {
            throw ActionException::noOp();
        }

        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$oldStatus->value] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw ActionException::invalidStatusTransition();
        }

        $target->setStatus($newStatus);
        $target->setBlockedUntil($newStatus === UserStatus::Blocked ? $blockedUntil : null);
        if ($newStatus === UserStatus::Blocked || $newStatus === UserStatus::Denied) {
            $target->setRegcode(null);
            $target->setRegcodeExpiresAt(null);
        }
        $this->em->flush();

        if ($oldStatus === UserStatus::EmailVerified && $newStatus === UserStatus::Active) {
            $this->dispatcher->dispatch(EntityAction::ApproveUser, (int) $target->getId());
            $this->welcomeEmail->send(['user' => $target]);
        }

        if ($newStatus === UserStatus::Denied) {
            $this->dispatcher->dispatch(EntityAction::DenyUser, (int) $target->getId());
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
        if ($target->getBlockedUntil() instanceof DateTimeImmutable) {
            $meta['until'] = $target->getBlockedUntil()->format('Y-m-d H:i');
        }
        $this->activityService->log($type, $actor, $meta);
    }

    public function requestDeletion(User $actor, User $target): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        $this->markDeleted($target);

        $this->activityService->log(AdminMemberStatusChanged::TYPE, $actor, [
            'user_id' => $target->getId(),
            'old' => $oldStatus->value,
            'new' => UserStatus::Deleted->value,
        ]);
    }

    public function requestOwnDeletion(User $user): void
    {
        if ($user->getRole() === UserRole::System) {
            throw ActionException::systemUser();
        }

        $this->markDeleted($user);
    }

    public function findDeletionBlocker(User $user): ?string
    {
        foreach ($this->deletionBlockers as $blocker) {
            $reason = $blocker->blockReason($user);
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    public function restore(User $actor, User $target): void
    {
        $this->assertStructural($actor, $target);

        $oldStatus = $target->getStatus();
        if ($oldStatus === UserStatus::Active) {
            throw ActionException::noOp();
        }
        if ($target->isErased()) {
            throw ActionException::erased();
        }

        $target->setStatus(UserStatus::Active);
        $target->setDeletionRequestedAt(null);
        $this->em->persist($target);
        $this->em->flush();

        $this->activityService->log(AdminMemberStatusChanged::TYPE, $actor, [
            'user_id' => $target->getId(),
            'old' => $oldStatus->value,
            'new' => UserStatus::Active->value,
        ]);
    }

    private function markDeleted(User $target): void
    {
        if ($target->getStatus() === UserStatus::Deleted) {
            throw ActionException::noOp();
        }

        $reason = $this->findDeletionBlocker($target);
        if ($reason !== null) {
            throw ActionException::deletionBlocked($reason);
        }

        $target->setStatus(UserStatus::Deleted);
        $target->setDeletionRequestedAt($this->clock->now());
        $target->setRegcode(null);
        $target->setRegcodeExpiresAt(null);
        $this->em->persist($target);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::DeleteUser, (int) $target->getId());
    }

    private function assertStructural(User $actor, User $target): void
    {
        if ($actor->getId() === $target->getId()) {
            throw ActionException::selfModification();
        }

        if ($target->getRole() === UserRole::System) {
            throw ActionException::systemUser();
        }
    }
}
