<?php declare(strict_types=1);

namespace App\Item;

readonly class AttachControlType
{
    /**
     * @param list<AttachSlot> $slots
     */
    public function __construct(
        private string $key,
        private string $labelKey,
        private string $attachHeadingKey,
        private string $attachHelpKey,
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

    public function getAttachHeadingKey(): string
    {
        return $this->attachHeadingKey;
    }

    public function getAttachHelpKey(): string
    {
        return $this->attachHelpKey;
    }

    public function getPickerHtml(): string
    {
        return $this->pickerHtml;
    }

    /** @return list<AttachSlot> */
    public function getSlots(): array
    {
        return $this->slots;
    }
}
