<?php declare(strict_types=1);

namespace App\Item;

/**
 * View-model for the event detail attach control: the event it targets and the active item
 * types a steward can attach. A type dropdown is shown only when more than one type is active.
 */
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
