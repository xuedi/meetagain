<?php declare(strict_types=1);

namespace App\Circulation;

use App\Entity\CirculationLedgerEntry;
use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LedgerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CirculationLedgerEntryRepository $entries,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        CirculationLedgerEntryType $entryType,
        string $context,
        string $itemType,
        int $itemId,
        DateTimeImmutable $occurredAt,
        ?int $copyId = null,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $actorUserId = null,
        array $payload = [],
    ): CirculationLedgerEntry {
        $entry = new CirculationLedgerEntry(
            $entryType,
            $context,
            $itemType,
            $itemId,
            $occurredAt,
            $copyId,
            $fromUserId,
            $toUserId,
            $actorUserId,
            $payload,
        );

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<CirculationLedgerEntry> newest first
     */
    public function getTimeline(string $context, string $itemType, int $limit, int $offset, ?array $allowedItemIds = null): array
    {
        return $this->entries->findTimeline($context, $itemType, $limit, $offset, $allowedItemIds);
    }

    /**
     * @param list<int>|null $allowedItemIds
     */
    public function countTimeline(string $context, string $itemType, ?array $allowedItemIds = null): int
    {
        return $this->entries->countTimeline($context, $itemType, $allowedItemIds);
    }

    public function getRevision(string $context): ?int
    {
        return $this->entries->getMaxId($context);
    }
}
