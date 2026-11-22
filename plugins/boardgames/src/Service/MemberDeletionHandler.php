<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Entity\GamePledge;

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

        foreach ([
            [GamePledge::class,    'user'],
            [GameOwnership::class, 'user'],
            [BringRequest::class,  'requestedBy'],
            [BringRequest::class,  'ownerUser'],
        ] as [$entity, $field]) {
            $this->em
                ->createQuery(sprintf('DELETE FROM %s r WHERE IDENTITY(r.%s) = :user', $entity, $field))
                ->setParameter('user', $entityId)
                ->execute();
        }
    }
}
