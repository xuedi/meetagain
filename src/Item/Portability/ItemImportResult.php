<?php declare(strict_types=1);

namespace App\Item\Portability;

readonly class ItemImportResult
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
