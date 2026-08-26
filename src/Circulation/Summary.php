<?php declare(strict_types=1);

namespace App\Circulation;

final readonly class Summary
{
    public function __construct(
        public string $itemType,
        public int $itemId,
        public int $totalCopies,
        public int $availableCopies,
        public int $queueLength,
        public bool $viewerHolds,
        public ?int $viewerQueuePosition,
        public bool $viewerHasOpenRequest,
    ) {}

    public function hasCopies(): bool
    {
        return $this->totalCopies > 0;
    }

    public function isAnyAvailable(): bool
    {
        return $this->availableCopies > 0;
    }
}
