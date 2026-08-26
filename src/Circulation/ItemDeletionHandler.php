<?php declare(strict_types=1);

namespace App\Circulation;

use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationHandoverStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Enum\ItemAction;
use App\Item\ActionInterface;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class ItemDeletionHandler implements ActionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private CirculationCopyRepository $copies,
        private CirculationRequestRepository $requests,
        private CirculationHandoverRepository $handovers,
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
            if ($handover->getStatus() !== CirculationHandoverStatus::Open) {
                continue;
            }
            $handover->setStatus(CirculationHandoverStatus::Cancelled);
            $handover->setCancelledAt($now);
        }

        $contexts = array_unique(array_map(static fn($copy): string => $copy->getContext(), $copies));
        foreach ($contexts as $context) {
            foreach ($this->requests->findOpenForItem($context, $itemType, $itemId) as $request) {
                $request->setStatus(CirculationRequestStatus::Cancelled);
                $request->setOfferedCopy(null);
                $request->setOfferedAt(null);
            }
        }

        foreach ($copies as $copy) {
            if (!$copy->getStatus()->isCirculating()) {
                continue;
            }
            $copy->setStatus(CirculationCopyStatus::Retired);
        }

        $this->em->flush();

        foreach ($copies as $copy) {
            $this->ledger->append(
                CirculationLedgerEntryType::Retired,
                $copy->getContext(),
                $itemType,
                $itemId,
                $now,
                $copy->getId(),
                null,
                null,
                null,
                ['reason' => 'item_deleted'],
            );
        }
    }
}
