<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

final class NamePicks
{
    private const int RANK_PREFERRED_SHORT = 4;
    private const int RANK_PREFERRED = 3;
    private const int RANK_PLAIN = 2;
    private const int RANK_SHORT = 1;

    /** @var array<int, array<string, array{name: string, rank: int}>> */
    private array $picks = [];

    public function offer(int $geonameId, string $language, string $name, bool $preferred, bool $short, bool $colloquial, bool $historic): void
    {
        if ($colloquial || $historic || trim($name) === '') {
            return;
        }

        $rank = match (true) {
            $preferred && $short => self::RANK_PREFERRED_SHORT,
            $preferred => self::RANK_PREFERRED,
            $short => self::RANK_SHORT,
            default => self::RANK_PLAIN,
        };
        $current = $this->picks[$geonameId][$language]['rank'] ?? 0;
        if ($rank > $current) {
            $this->picks[$geonameId][$language] = ['name' => trim($name), 'rank' => $rank];
        }
    }

    /**
     * @return array<int, array<string, string>> name per language, keyed by GeoNames id
     */
    public function all(): array
    {
        return array_map(static fn(array $byLanguage): array => array_map(static fn(array $pick): string => $pick['name'], $byLanguage), $this->picks);
    }
}
