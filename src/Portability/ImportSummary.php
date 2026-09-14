<?php declare(strict_types=1);

namespace App\Portability;

readonly class ImportSummary
{
    /**
     * @param array<string, array<string, int>> $counts kind => outcome value => rows
     * @param list<string> $missingPlugins
     */
    public function __construct(
        public array $counts = [],
        public array $missingPlugins = [],
        public int $weeksShifted = 0,
        public bool $siteApplied = false,
    ) {}

    public function get(string $kind, Outcome $outcome): int
    {
        return $this->counts[$kind][$outcome->value] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getLosses(): array
    {
        $losses = [];
        foreach (array_keys($this->counts) as $kind) {
            $lost = $this->get($kind, Outcome::Skipped) + $this->get($kind, Outcome::Dropped);
            if ($lost > 0) {
                $losses[$kind] = $lost;
            }
        }

        return $losses;
    }
}
