<?php declare(strict_types=1);

namespace Module\Ballot\Internal;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Internal\Entity\Ballot;
use Module\Ballot\Internal\Entity\BallotVote;
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
            ->createQuery(sprintf(
                'DELETE FROM %s v WHERE IDENTITY(v.user) = :user AND v.ballot IN (SELECT b.id FROM %s b WHERE b.status = :open)',
                BallotVote::class,
                Ballot::class,
            ))
            ->setParameter('user', $entityId)
            ->setParameter('open', BallotStatus::Open)
            ->execute();
    }
}
