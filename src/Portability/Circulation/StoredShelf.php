<?php declare(strict_types=1);

namespace App\Portability\Circulation;

use DateTimeImmutable;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;

readonly class StoredShelf
{
    private const string KEY_DATE = 'Y-m-d H:i:s';

    /** @var array<string, int> */
    private array $copyIds;

    /** @var array<string, true> */
    private array $requests;

    /** @var array<string, int> */
    private array $handoverIds;

    /** @var array<string, true> */
    private array $ledger;

    public function __construct(PortableShelf $shelf = new PortableShelf())
    {
        $copyIds = [];
        foreach ($shelf->copies as $copy) {
            $copyIds[$this->copyKey($copy)] = $copy->ref;
        }

        $requests = [];
        foreach ($shelf->requests as $request) {
            foreach ($this->requestKeys($request) as $key) {
                $requests[$key] = true;
            }
        }

        $handoverIds = [];
        foreach ($shelf->handovers as $handover) {
            $handoverIds[$this->handoverKey($handover->copyRef, $handover->toUserId, $handover->openedAt)] = $handover->ref;
        }

        $ledger = [];
        foreach ($shelf->ledger as $entry) {
            $ledger[$this->ledgerKey($entry->type, $entry->itemType, $entry->itemId, $entry->occurredAt)] = true;
        }

        $this->copyIds = $copyIds;
        $this->requests = $requests;
        $this->handoverIds = $handoverIds;
        $this->ledger = $ledger;
    }

    public function copyId(PortableCopy $copy): ?int
    {
        return $this->copyIds[$this->copyKey($copy)] ?? null;
    }

    public function hasRequest(PortableRequest $request): bool
    {
        return array_any($this->requestKeys($request), fn(string $key): bool => isset($this->requests[$key]));
    }

    public function handoverId(int $copyId, ?int $toUserId, ?DateTimeImmutable $openedAt): ?int
    {
        return $this->handoverIds[$this->handoverKey($copyId, $toUserId, $openedAt)] ?? null;
    }

    public function hasLedgerEntry(LedgerEntryType $type, string $itemType, int $itemId, DateTimeImmutable $occurredAt): bool
    {
        return isset($this->ledger[$this->ledgerKey($type, $itemType, $itemId, $occurredAt)]);
    }

    private function copyKey(PortableCopy $copy): string
    {
        return implode('|', [$copy->itemType, $copy->itemId, $copy->donatedAt->format(self::KEY_DATE)]);
    }

    /**
     * @return list<string> the request itself, plus its member's one open place on the item while it is open
     */
    private function requestKeys(PortableRequest $request): array
    {
        $keys = [implode('|', [$request->itemType, $request->itemId, $request->userId, $request->requestedAt->format(self::KEY_DATE)])];
        if ($request->status->isOpen()) {
            $keys[] = implode('|', [$request->itemType, $request->itemId, $request->userId, 'open']);
        }

        return $keys;
    }

    private function handoverKey(int $copyId, ?int $toUserId, ?DateTimeImmutable $openedAt): string
    {
        return implode('|', [$copyId, $toUserId ?? '', $openedAt?->format(self::KEY_DATE) ?? '']);
    }

    private function ledgerKey(LedgerEntryType $type, string $itemType, int $itemId, DateTimeImmutable $occurredAt): string
    {
        return implode('|', [$type->value, $itemType, $itemId, $occurredAt->format(self::KEY_DATE)]);
    }
}
