<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

use Plugin\Ranking\Enum\Archetype;

readonly class RankPreset
{
    /**
     * @param list<RankPresetEntry> $entries
     */
    public function __construct(
        public string $key,
        public Archetype $archetype,
        public string $displayNameKey,
        public array $entries,
    ) {}
}
