<?php declare(strict_types=1);

namespace App\Theme;

use App\Publisher\PluginSettings\Data;
use Override;

final class Config implements Data
{
    public const array KEYS = [
        'color_primary',
        'color_link',
        'color_info',
        'color_success',
        'color_warning',
        'color_danger',
        'color_text_grey',
        'color_text_grey_light',
    ];

    /**
     * @param array<string, string> $colors
     */
    private function __construct(
        private readonly array $colors,
    ) {}

    #[Override]
    public static function fromArray(array $raw): static
    {
        $colors = [];
        foreach (self::KEYS as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}\z/', $value) === 1) {
                $colors[$key] = strtolower($value);
            }
        }

        return new self($colors);
    }

    #[Override]
    public function toArray(): array
    {
        return $this->colors;
    }

    public function get(string $key): ?string
    {
        return $this->colors[$key] ?? null;
    }

    /**
     * @param array<array-key, mixed> $colors
     */
    public function with(array $colors): self
    {
        return new self([...$this->colors, ...self::fromArray($colors)->colors]);
    }
}
