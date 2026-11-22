<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Entity\User;
use App\EntityActionInterface;
use App\Enum\EntityAction;
use App\Service\Member\DeletionBlockerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Override;

final readonly class MemberDeletionHandler implements EntityActionInterface, DeletionBlockerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private CopyRepository $copies,
        private HandoverRepository $handovers,
        private HandoverService $handoverService,
    ) {}

    #[Override]
    public function blockReason(User $user): ?string
    {
        if ($this->copies->findHeldBy((int) $user->getId()) !== [] || $this->handovers->findOpenForUser($user) !== []) {
            return 'circulation.deletion_blocked_holds_copy';
        }

        return null;
    }

    #[Override]
    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if ($action !== EntityAction::EraseUser) {
            return;
        }

        $user = $this->em->find(User::class, $entityId);
        if ($user instanceof User) {
            foreach ($this->handovers->findOpenForUser($user) as $handover) {
                $this->handoverService->cancel($handover, $user);
            }
        }

        $this->em->createQuery(sprintf('DELETE FROM %s r WHERE IDENTITY(r.user) = :user', Request::class))->setParameter('user', $entityId)->execute();
    }
}
