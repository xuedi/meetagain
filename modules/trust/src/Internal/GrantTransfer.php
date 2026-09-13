<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Trust\Contract\GrantTransferInterface;
use Module\Trust\Contract\PortableGrant;
use Module\Trust\Internal\Entity\TrustGrant;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Override;

final readonly class GrantTransfer implements GrantTransferInterface
{
    public function __construct(
        private TrustGrantRepository $repository,
        private EntityManagerInterface $entityManager,
        private ScoreProvider $scoreProvider,
    ) {}

    #[Override]
    public function exportGrants(array $contexts): array
    {
        if ($contexts === []) {
            return [];
        }

        return array_map(
            static fn(TrustGrant $grant): PortableGrant => new PortableGrant(
                $grant->getContext(),
                (int) $grant->getFromUser()->getId(),
                (int) $grant->getToUser()->getId(),
                $grant->getLevel(),
                $grant->getCreatedAt(),
                $grant->getUpdatedAt(),
            ),
            $this->repository->findForContexts($contexts),
        );
    }

    #[Override]
    public function restoreGrant(PortableGrant $grant): void
    {
        if ($grant->fromUserId === $grant->toUserId) {
            throw new InvalidArgumentException('A member cannot vouch for themselves.');
        }

        $existing = $this->repository->findEdge($grant->context, $grant->fromUserId, $grant->toUserId);
        if ($existing instanceof TrustGrant) {
            $existing->setLevel($grant->level, $grant->updatedAt);
        } else {
            $restored = new TrustGrant(
                $grant->context,
                $this->entityManager->getReference(User::class, $grant->fromUserId),
                $this->entityManager->getReference(User::class, $grant->toUserId),
                $grant->level,
                $grant->createdAt,
            );
            $restored->setLevel($grant->level, $grant->updatedAt);
            $this->entityManager->persist($restored);
        }

        $this->entityManager->flush();
        $this->scoreProvider->invalidate($grant->context);
    }
}
