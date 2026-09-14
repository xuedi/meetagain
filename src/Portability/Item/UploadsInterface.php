<?php declare(strict_types=1);

namespace App\Portability\Item;

/**
 * An item type whose rows are member uploads. The exporter leaves out every row whose uploader did
 * not grant uploads, before any section reads the scope.
 */
interface UploadsInterface
{
    /**
     * @param list<int> $itemIds
     * @return array<int, int|null> item id => uploader user id
     */
    public function getUploaderIds(array $itemIds): array;
}
