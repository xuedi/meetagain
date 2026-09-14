<?php declare(strict_types=1);

namespace App\Portability\Item;

readonly class ImportResult
{
    /**
     * @param array<int, int> $refToItemId source row ref => item id on this instance
     */
    public function __construct(
        public array $refToItemId,
        public int $created,
        public int $matched,
    ) {}
}
