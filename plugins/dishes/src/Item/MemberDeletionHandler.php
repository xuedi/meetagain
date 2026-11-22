<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Dishes\Entity\DishLike;

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

        foreach ([[DishLike::class, 'userId']] as [$entity, $field]) {
            $this->em
                ->createQuery(sprintf('DELETE FROM %s r WHERE r.%s = :user', $entity, $field))
                ->setParameter('user', $entityId)
                ->execute();
        }
    }
}
