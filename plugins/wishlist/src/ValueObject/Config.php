<?php declare(strict_types=1);

namespace Plugin\Wishlist\ValueObject;

use App\Publisher\PluginSettings\Data;

final class Config implements Data
{
    private bool $navLink = true;

    public function isNavLink(): bool
    {
        return $this->navLink;
    }

    public function setNavLink(bool $navLink): static
    {
        $this->navLink = $navLink;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'navLink' => $this->navLink,
        ];
    }

    public static function fromArray(array $raw): static
    {
        return new self()->setNavLink((bool) ($raw['navLink'] ?? true));
    }
}
