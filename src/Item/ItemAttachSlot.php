<?php declare(strict_types=1);

namespace App\Item;

/**
 * One extra attach action a subsystem contributes to the attach control
 * (e.g. "put it to a vote", "pick from wishlist"): a target url plus a
 * translation key for the button label and an optional Font Awesome icon name.
 */
readonly class ItemAttachSlot
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
