<?php declare(strict_types=1);

namespace Plugin\Books\ValueObject;

use App\Publisher\PluginSettings\Data;

final class Config implements Data
{
    private bool $circulation = false;

    private bool $trustSystem = false;

    public function isCirculation(): bool
    {
        return $this->circulation;
    }

    public function setCirculation(bool $circulation): static
    {
        $this->circulation = $circulation;

        return $this;
    }

    public function isTrustSystem(): bool
    {
        return $this->trustSystem;
    }

    public function setTrustSystem(bool $trustSystem): static
    {
        $this->trustSystem = $trustSystem;

        return $this;
    }

    public function isTrustActive(): bool
    {
        return $this->circulation && $this->trustSystem;
    }

    public function toArray(): array
    {
        return [
            'circulation' => $this->circulation,
            'trustSystem' => $this->trustSystem,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->setCirculation((bool) ($raw['circulation'] ?? false));
        $config->setTrustSystem((bool) ($raw['trustSystem'] ?? false));

        return $config;
    }
}
