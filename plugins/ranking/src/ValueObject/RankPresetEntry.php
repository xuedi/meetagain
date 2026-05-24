<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

readonly class RankPresetEntry
{
    public function __construct(
        public string $label,
        public ?string $colorHex = null,
        public ?string $labelKey = null,
    ) {}
}
