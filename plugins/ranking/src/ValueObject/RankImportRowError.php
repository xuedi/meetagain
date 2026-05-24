<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

readonly class RankImportRowError
{
    public function __construct(
        public int $rowNumber,
        public string $message,
    ) {}
}
