<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

readonly class RankImportReport
{
    /**
     * @param list<RankImportRowError> $errors
     */
    public function __construct(
        public int $importedCount,
        public array $errors,
    ) {}

    public function errorCount(): int
    {
        return count($this->errors);
    }
}
