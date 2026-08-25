<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\Entity\TrustGrant;
use Module\Trust\Internal\Repository\TrustGrantRepository;

final readonly class VouchService
{
    public function __construct(
        private TrustGrantRepository $repository,
        private EntityManagerInterface $entityManager,
        private ContextRegistry $registry,
        private ScoreProvider $scoreProvider,
    ) {}

    public function grant(string $context, int $fromUserId, int $toUserId, TrustLevel $level): void
    {
        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('A member cannot vouch for themselves.');
        }
        if (!$this->registry->exists($context)) {
            throw new InvalidArgumentException('Unknown trust context.');
        }

        $existing = $this->repository->findEdge($context, $fromUserId, $toUserId);
        $now = new DateTimeImmutable('now');

        if ($existing instanceof TrustGrant) {
            $existing->setLevel($level, $now);
        } else {
            $this->entityManager->persist(new TrustGrant(
                $context,
                $this->reference($fromUserId),
                $this->reference($toUserId),
                $level,
                $now,
            ));
        }

        $this->entityManager->flush();
        $this->scoreProvider->invalidate($context);
    }

    public function revoke(string $context, int $fromUserId, int $toUserId): void
    {
        $existing = $this->repository->findEdge($context, $fromUserId, $toUserId);
        if (!$existing instanceof TrustGrant) {
            return;
        }

        $this->entityManager->remove($existing);
        $this->entityManager->flush();
        $this->scoreProvider->invalidate($context);
    }

    /**
     * @return array<int, TrustLevel>
     */
    public function getOutgoing(string $context, int $fromUserId): array
    {
        return $this->repository->findOutgoing($context, $fromUserId);
    }

    /**
     * @return array<int, int>
     */
    public function getVouchCounts(string $context): array
    {
        return $this->repository->countIncomingByUser($context);
    }

    private function reference(int $userId): User
    {
        return $this->entityManager->getReference(User::class, $userId);
    }
}
