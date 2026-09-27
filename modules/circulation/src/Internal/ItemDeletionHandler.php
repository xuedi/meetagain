<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Enum\ItemAction;
use App\Item\ActionInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Circulation\Internal\Repository\RequestRepository;
use Override;

final readonly class ItemDeletionHandler implements ActionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private CopyRepository $copies,
        private RequestRepository $requests,
        private HandoverRepository $handovers,
        private LedgerService $ledger,
    ) {}

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted) {
            return;
        }

        $copies = $this->copies->findByItem($itemType, $itemId);
        $copyIds = array_map(static fn($copy): int => (int) $copy->getId(), $copies);
        $now = new DateTimeImmutable();

        foreach ($this->handovers->findByCopyIds($copyIds) as $handover) {
            if ($handover->getStatus() !== HandoverStatus::Open) {
                continue;
            }
            $handover->setStatus(HandoverStatus::Cancelled);
            $handover->setCancelledAt($now);
        }

        $contexts = array_unique(array_map(static fn($copy): string => $copy->getContext(), $copies));
        foreach ($contexts as $context) {
            foreach ($this->requests->findOpenForItem($context, $itemType, $itemId) as $request) {
                $request->setStatus(RequestStatus::Cancelled);
                $request->setOfferedCopy(null);
                $request->setOfferedAt(null);
            }
        }

        foreach ($copies as $copy) {
            if (!$copy->getStatus()->isCirculating()) {
                continue;
            }
            $copy->setStatus(CopyStatus::Retired);
        }

        $this->em->flush();

        foreach ($copies as $copy) {
            $this->ledger->append(LedgerEntryType::Retired, $copy->getContext(), $itemType, $itemId, $now, $copy->getId(), null, null, null, [
                'reason' => 'item_deleted',
            ]);
        }
    }
}
