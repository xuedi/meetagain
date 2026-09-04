<?php declare(strict_types=1);

namespace Plugin\Boardgames\ValueObject;

use App\Publisher\PluginSettings\Data;
use Plugin\Boardgames\Enum\ExternalSource;

final class Config implements Data
{
    private ?ExternalSource $adapter = null;

    private ?string $encryptedBggToken = null;

    private bool $circulation = false;

    private bool $trustSystem = false;

    public function getAdapter(): ?ExternalSource
    {
        return $this->adapter;
    }

    public function setAdapter(?ExternalSource $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function getEncryptedBggToken(): ?string
    {
        return $this->encryptedBggToken;
    }

    public function setEncryptedBggToken(?string $encryptedBggToken): static
    {
        $this->encryptedBggToken = $encryptedBggToken === '' ? null : $encryptedBggToken;

        return $this;
    }

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
            'adapter' => $this->adapter?->value,
            'encryptedBggToken' => $this->encryptedBggToken,
            'circulation' => $this->circulation,
            'trustSystem' => $this->trustSystem,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $adapter = $raw['adapter'] ?? null;

        $config = new self();
        $config->setAdapter(is_string($adapter) ? ExternalSource::tryFrom($adapter) : null);
        $config->setEncryptedBggToken(isset($raw['encryptedBggToken']) ? (string) $raw['encryptedBggToken'] : null);
        $config->setCirculation((bool) ($raw['circulation'] ?? false));
        $config->setTrustSystem((bool) ($raw['trustSystem'] ?? false));

        return $config;
    }
}
