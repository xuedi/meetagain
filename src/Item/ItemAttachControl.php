<?php declare(strict_types=1);

namespace App\Item;

readonly class ItemAttachControl
{
    /**
     * @param list<ItemAttachControlType> $types
     */
    public function __construct(
        private int $eventId,
        private array $types,
    ) {}

    public function getEventId(): int
    {
        return $this->eventId;
    }

    /** @return list<ItemAttachControlType> */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function isEmpty(): bool
    {
        return $this->types === [];
    }

    public function hasMultipleTypes(): bool
    {
        return count($this->types) > 1;
    }
}
