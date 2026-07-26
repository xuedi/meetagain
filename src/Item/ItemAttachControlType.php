<?php declare(strict_types=1);

namespace App\Item;

readonly class ItemAttachControlType
{
    /**
     * @param list<ItemAttachSlot> $slots
     */
    public function __construct(
        private string $key,
        private string $labelKey,
        private string $pickerHtml,
        private array $slots,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getPickerHtml(): string
    {
        return $this->pickerHtml;
    }

    /** @return list<ItemAttachSlot> */
    public function getSlots(): array
    {
        return $this->slots;
    }
}
