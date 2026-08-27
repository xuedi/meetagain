<?php declare(strict_types=1);

namespace App\Circulation;

use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;

final readonly class LedgerReplay
{
    public function __construct(
        private CirculationLedgerEntryRepository $entries,
    ) {}

    /**
     * @return array<int, CopyState> derived copy state keyed by copy id
     */
    public function rebuild(?string $context = null): array
    {
        $states = [];
        foreach ($this->entries->findChronological($context) as $entry) {
            $copyId = $entry->getCopyId();
            if ($copyId === null) {
                continue;
            }

            $current = $states[$copyId] ?? new CopyState(null, null, CirculationCopyStatus::Available);
            $states[$copyId] = match ($entry->getEntryType()) {
                CirculationLedgerEntryType::Donated => new CopyState(
                    $entry->getActorUserId(),
                    $entry->getOccurredAt(),
                    CirculationCopyStatus::Available,
                ),
                CirculationLedgerEntryType::MarkedFinished,
                CirculationLedgerEntryType::HandoverCancelled => $current->with(status: CirculationCopyStatus::Available),
                CirculationLedgerEntryType::HandoverOpened => $current->with(status: CirculationCopyStatus::InHandover),
                CirculationLedgerEntryType::HandoverCompleted => new CopyState(
                    $entry->getToUserId(),
                    $entry->getOccurredAt(),
                    CirculationCopyStatus::Held,
                ),
                CirculationLedgerEntryType::Retired => $current->with(status: CirculationCopyStatus::Retired),
                CirculationLedgerEntryType::Lost => $current->with(status: CirculationCopyStatus::Lost),
                default => $current,
            };
        }

        return $states;
    }
}
