<?php declare(strict_types=1);

namespace Plugin\Dishes\ValueObject;

use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective dishes settings: an optional footer text shown at the bottom of the dish list,
 * held per locale. The neutral default is no footer at all.
 */
final class Config implements PluginSettingsData
{
    /** @var array<string, string> locale => footer text */
    private array $footerText = [];

    /** @return array<string, string> */
    public function getFooterText(): array
    {
        return $this->footerText;
    }

    /** @param array<array-key, mixed> $footerText */
    public function setFooterText(array $footerText): static
    {
        $clean = [];
        foreach ($footerText as $locale => $text) {
            $trimmed = trim((string) $text);
            if ($trimmed === '') {
                continue;
            }
            $clean[(string) $locale] = $trimmed;
        }
        $this->footerText = $clean;

        return $this;
    }

    public function getFooterFor(string $locale): string
    {
        return $this->footerText[$locale] ?? '';
    }

    public function toArray(): array
    {
        return ['footerText' => $this->footerText];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $footer = $raw['footerText'] ?? [];
        if (is_array($footer)) {
            $config->setFooterText($footer);
        }

        return $config;
    }
}
