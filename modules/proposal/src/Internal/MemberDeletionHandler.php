<?php declare(strict_types=1);

namespace Module\Proposal\Internal;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Proposal\Contract\Status;
use Module\Proposal\Internal\Entity\Change;
use Module\Proposal\Internal\Entity\Suggestion;
use Override;

final readonly class MemberDeletionHandler implements EntityActionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if ($action !== EntityAction::EraseUser) {
            return;
        }

        foreach ([Change::class, Suggestion::class] as $entity) {
            $this->em
                ->createQuery(sprintf('DELETE FROM %s p WHERE IDENTITY(p.proposedBy) = :user AND p.status = :pending', $entity))
                ->setParameter('user', $entityId)
                ->setParameter('pending', Status::Pending)
                ->execute();
        }
    }
}
