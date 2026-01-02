<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Repository\UserBlockRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

readonly class BlockingService
{
    public function __construct(
        private UserBlockRepository $blockRepo,
        private EntityManagerInterface $em,
        private ActivityService $activityService,
    ) {
    }

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->getId() === $blocked->getId()) {
            throw new InvalidArgumentException('Cannot block yourself');
        }

        if ($this->blockRepo->isBlocked($blocker, $blocked)) {
            return; // Already blocked
        }

        // Remove any following relationship in both directions
        $blocker->removeFollowing($blocked);
        $blocker->removeFollower($blocked);
        $blocked->removeFollowing($blocker);
        $blocked->removeFollower($blocker);

        $block = new UserBlock();
        $block->setBlocker($blocker);
        $block->setBlocked($blocked);
        $block->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($block);
        $this->em->persist($blocker);
        $this->em->persist($blocked);
        $this->em->flush();

        $this->activityService->log(
            ActivityType::BlockedUser,
            $blocker,
            ['user_id' => $blocked->getId()]
        );
    }

    public function unblock(User $blocker, User $blocked): void
    {
        $block = $this->blockRepo->findOneBy([
            'blocker' => $blocker,
            'blocked' => $blocked,
        ]);

        if ($block === null) {
            return; // Not blocked
        }

        $this->em->remove($block);
        $this->em->flush();

        $this->activityService->log(
            ActivityType::UnblockedUser,
            $blocker,
            ['user_id' => $blocked->getId()]
        );
    }

    public function isBlocked(User $user1, User $user2): bool
    {
        return $this->blockRepo->isBlockedEitherWay($user1, $user2);
    }

    /**
     * Check if blocker has blocked the blocked user (one-way check).
     */
    public function hasBlocked(User $blocker, User $blocked): bool
    {
        return $this->blockRepo->isBlocked($blocker, $blocked);
    }

    public function canInteract(User $actor, User $target): bool
    {
        return !$this->isBlocked($actor, $target);
    }

    /**
     * @return UserBlock[]
     */
    public function getBlockedUsers(User $user): array
    {
        return $this->blockRepo->getBlockedUsers($user);
    }

    /**
     * Get all user IDs that should be excluded from lists.
     *
     * @return int[]
     */
    public function getExcludedUserIds(User $user): array
    {
        return $this->blockRepo->getAllBlockRelatedIds($user);
    }
}
