<?php declare(strict_types=1);

namespace Plugin\Glossary\ValueObject;

use App\Publisher\PluginSettings\Data;
final class Config implements Data
{
    private bool $secondaryEnabled = false;
    private ?string $secondaryLabel = null;
    private ?string $primaryLabel = null;
    private ?string $definitionLabel = null;

    public function isSecondaryEnabled(): bool
    {
        return $this->secondaryEnabled;
    }

    public function setSecondaryEnabled(bool $secondaryEnabled): static
    {
        $this->secondaryEnabled = $secondaryEnabled;

        return $this;
    }

    public function getSecondaryLabel(): ?string
    {
        return $this->secondaryLabel;
    }

    public function setSecondaryLabel(?string $secondaryLabel): static
    {
        $this->secondaryLabel = $this->trimToNull($secondaryLabel);

        return $this;
    }

    public function getPrimaryLabel(): ?string
    {
        return $this->primaryLabel;
    }

    public function setPrimaryLabel(?string $primaryLabel): static
    {
        $this->primaryLabel = $this->trimToNull($primaryLabel);

        return $this;
    }

    public function getDefinitionLabel(): ?string
    {
        return $this->definitionLabel;
    }

    public function setDefinitionLabel(?string $definitionLabel): static
    {
        $this->definitionLabel = $this->trimToNull($definitionLabel);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'secondaryEnabled' => $this->secondaryEnabled,
            'secondaryLabel' => $this->secondaryLabel,
            'primaryLabel' => $this->primaryLabel,
            'definitionLabel' => $this->definitionLabel,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->secondaryEnabled = (bool) ($raw['secondaryEnabled'] ?? false);
        $config->secondaryLabel = self::trimToNullStatic($raw['secondaryLabel'] ?? null);
        $config->primaryLabel = self::trimToNullStatic($raw['primaryLabel'] ?? null);
        $config->definitionLabel = self::trimToNullStatic($raw['definitionLabel'] ?? null);

        return $config;
    }

    private function trimToNull(?string $value): ?string
    {
        return self::trimToNullStatic($value);
    }

    private static function trimToNullStatic(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
