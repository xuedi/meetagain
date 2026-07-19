<?php declare(strict_types=1);

namespace Plugin\Books\Service;

interface IsbnLookupInterface
{
    public function lookup(string $isbn): ?BookData;
}
