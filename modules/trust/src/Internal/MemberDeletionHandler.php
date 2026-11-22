<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Internal\Entity\TrustGrant;
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

        $this->em
            ->createQuery(sprintf('DELETE FROM %s g WHERE IDENTITY(g.fromUser) = :user OR IDENTITY(g.toUser) = :user', TrustGrant::class))
            ->setParameter('user', $entityId)
            ->execute();
    }
}
