<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

interface IsbnLookupInterface
{
    public function lookup(string $isbn): ?BookData;
}
