<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\TrustAction;
use Override;

final readonly class LedgerActionSource implements ActionSourceInterface
{
    public const string HANDOVER_ACTION = 'circulation_handover_completed';
    public const string DONATION_ACTION = 'circulation_donation';
    public const int HANDOVER_POINTS = 5;
    public const int DONATION_POINTS = 25;

    public function __construct(
        private ContextIndex $index,
        private CirculationLedgerEntryRepository $entries,
    ) {}

    #[Override]
    public function describeActions(string $context): iterable
    {
        if ($this->index->itemTypeFor($context) === null) {
            return;
        }

        yield new ActionDescriptor(self::HANDOVER_ACTION, 'circulation.trust_action_handover', self::HANDOVER_POINTS);
        yield new ActionDescriptor(self::DONATION_ACTION, 'circulation.trust_action_donation', self::DONATION_POINTS);
    }

    #[Override]
    public function replay(string $context): iterable
    {
        if ($this->index->itemTypeFor($context) === null) {
            return;
        }

        foreach ($this->entries->findChronological($context) as $entry) {
            if ($entry->getEntryType() === CirculationLedgerEntryType::Donated) {
                $donorId = $entry->getActorUserId();
                if ($donorId !== null) {
                    yield new TrustAction($donorId, self::DONATION_ACTION, $entry->getOccurredAt());
                }

                continue;
            }

            if ($entry->getEntryType() !== CirculationLedgerEntryType::HandoverCompleted) {
                continue;
            }

            $receiverId = $entry->getToUserId();
            if ($receiverId !== null) {
                yield new TrustAction($receiverId, self::HANDOVER_ACTION, $entry->getOccurredAt());
            }

            $giverId = $entry->getFromUserId();
            if ($giverId !== null) {
                yield new TrustAction($giverId, self::HANDOVER_ACTION, $entry->getOccurredAt());
            }
        }
    }

    #[Override]
    public function getRevision(string $context): ?string
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        $maxId = $this->entries->getMaxId($context);

        return $maxId === null ? null : 'circulation-' . $maxId;
    }
}
