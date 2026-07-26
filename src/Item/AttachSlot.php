<?php declare(strict_types=1);

namespace App\Item;

readonly class AttachSlot
{
    public function __construct(
        private string $url,
        private string $labelKey,
        private ?string $icon = null,
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}
