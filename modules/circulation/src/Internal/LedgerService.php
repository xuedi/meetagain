<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;

final readonly class LedgerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LedgerEntryRepository $entries,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        LedgerEntryType $entryType,
        string $context,
        string $itemType,
        int $itemId,
        DateTimeImmutable $occurredAt,
        ?int $copyId = null,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $actorUserId = null,
        array $payload = [],
    ): LedgerEntry {
        $entry = new LedgerEntry($entryType, $context, $itemType, $itemId, $occurredAt, $copyId, $fromUserId, $toUserId, $actorUserId, $payload);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<LedgerEntry> newest first
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
