<?php declare(strict_types=1);

namespace Module\Trust\Contract;

final readonly class TrustActionBreakdown
{
    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public int $countedQuantity,
        public ?int $cap,
        public int $pointsPerUnit,
        public int $subtotal,
    ) {}

    public function isCapped(): bool
    {
        return $this->cap !== null && $this->quantity > $this->cap;
    }
}
