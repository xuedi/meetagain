<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;

final readonly class LedgerReplay
{
    public function __construct(
        private LedgerEntryRepository $entries,
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

            $current = $states[$copyId] ?? new CopyState(null, null, CopyStatus::Available);
            $states[$copyId] = match ($entry->getEntryType()) {
                LedgerEntryType::Donated => new CopyState($entry->getActorUserId(), $entry->getOccurredAt(), CopyStatus::Available),
                LedgerEntryType::MarkedFinished, LedgerEntryType::HandoverCancelled => $current->with(status: CopyStatus::Available),
                LedgerEntryType::HandoverOpened => $current->with(status: CopyStatus::InHandover),
                LedgerEntryType::HandoverCompleted => new CopyState($entry->getToUserId(), $entry->getOccurredAt(), CopyStatus::Held),
                LedgerEntryType::Retired => $current->with(status: CopyStatus::Retired),
                LedgerEntryType::Lost => $current->with(status: CopyStatus::Lost),
                default => $current,
            };
        }

        return $states;
    }
}
