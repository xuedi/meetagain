<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Wishlist\Entity\WishlistEntry;

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

        foreach ([[WishlistEntry::class, 'userId']] as [$entity, $field]) {
            $this->em
                ->createQuery(sprintf('DELETE FROM %s r WHERE r.%s = :user', $entity, $field))
                ->setParameter('user', $entityId)
                ->execute();
        }
    }
}
