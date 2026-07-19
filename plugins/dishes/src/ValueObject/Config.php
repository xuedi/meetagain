<?php declare(strict_types=1);

namespace Plugin\Dishes\ValueObject;

use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective dishes settings: an optional footer text shown at the bottom of the dish list
 * (held per locale) and whether the phonetic column is shown in the list. The neutral default
 * is no footer and no phonetic column.
 */
final class Config implements PluginSettingsData
{
    /** @var array<string, string> locale => footer text */
    private array $footerText = [];

    private bool $phoneticInList = false;

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

    public function isPhoneticInList(): bool
    {
        return $this->phoneticInList;
    }

    public function setPhoneticInList(bool $phoneticInList): static
    {
        $this->phoneticInList = $phoneticInList;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'footerText' => $this->footerText,
            'phoneticInList' => $this->phoneticInList,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $footer = $raw['footerText'] ?? [];
        if (is_array($footer)) {
            $config->setFooterText($footer);
        }
        $config->setPhoneticInList((bool) ($raw['phoneticInList'] ?? false));

        return $config;
    }
}
