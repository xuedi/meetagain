<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class DeliveryLogCollection
{
    /** @param DeliveryLog[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $size,
    ) {}

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }
}
