<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Override;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

final class ExplodingCache extends ArrayAdapter
{
    #[Override]
    public function getItem(mixed $key): CacheItem
    {
        throw new RuntimeException('The cache is unreachable.');
    }

    #[Override]
    public function deleteItem(mixed $key): bool
    {
        throw new RuntimeException('The cache is unreachable.');
    }
}
