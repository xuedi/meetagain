<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Service\Config\LanguageService;
use App\Service\Media\AltLocaleRequirementResolver;
use App\Service\Media\ImageAltStatusCache;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ImageAltStatusCacheTest extends TestCase
{
    public function testComputesAndStoresOnMiss(): void
    {
        // Arrange - image 1 has every required alt, image 2 misses 'de'.
        $complete = self::imageWithId(1);
        $complete->setAlt('english');
        $complete->setAltTranslation('de', 'deutsch');
        $incomplete = self::imageWithId(2);
        $incomplete->setAlt('english');
        $pool = new ArrayAdapter();
        $cache = $this->cache($pool, requiredLocales: ['en', 'de']);

        // Act
        $map = $cache->getMissingAltMap([$complete, $incomplete]);

        // Assert
        static::assertSame([1 => false, 2 => true], $map);
        static::assertTrue($pool->getItem('image_alt_status.2')->isHit());
        static::assertSame(['required' => ['en', 'de'], 'missing' => ['de']], $pool->getItem('image_alt_status.2')->get());
    }

    public function testCachedHitSkipsTheResolver(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $pool = new ArrayAdapter();
        $resolver = $this->createMock(AltLocaleRequirementResolver::class);
        $resolver->expects($this->once())->method('getRequiredAltLocalesForImages')->willReturn([1 => ['en']]);
        $cache = $this->cache($pool, resolver: $resolver);

        // Act
        $first = $cache->getMissingAltMap([$image]);
        $second = $cache->getMissingAltMap([$image]);

        // Assert
        static::assertSame([1 => true], $first);
        static::assertSame([1 => true], $second);
    }

    public function testWarmStoresEntriesForEveryImage(): void
    {
        // Arrange
        $pool = new ArrayAdapter();
        $cache = $this->cache($pool, requiredLocales: ['en']);

        // Act
        $cache->warm([self::imageWithId(1), self::imageWithId(2)]);

        // Assert
        static::assertTrue($pool->getItem('image_alt_status.1')->isHit());
        static::assertTrue($pool->getItem('image_alt_status.2')->isHit());
    }

    public function testInvalidateImageRemovesTheEntry(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $pool = new ArrayAdapter();
        $cache = $this->cache($pool, requiredLocales: ['en']);
        $cache->getMissingAltMap([$image]);

        // Act
        $cache->invalidateImage(1);

        // Assert
        static::assertFalse($pool->getItem('image_alt_status.1')->isHit());
    }

    public function testInvalidateAllClearsThePool(): void
    {
        // Arrange
        $pool = new ArrayAdapter();
        $cache = $this->cache($pool, requiredLocales: ['en']);
        $cache->getMissingAltMap([self::imageWithId(1), self::imageWithId(2)]);

        // Act
        $cache->invalidateAll();

        // Assert
        static::assertFalse($pool->getItem('image_alt_status.1')->isHit());
        static::assertFalse($pool->getItem('image_alt_status.2')->isHit());
    }

    public function testPoolFailureFallsBackToDirectComputationWithOneWarning(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItems')->willThrowException(new RuntimeException('backend down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $cache = $this->cache($pool, requiredLocales: ['en'], logger: $logger);

        // Act
        $first = $cache->getMissingAltMap([$image]);
        $second = $cache->getMissingAltMap([$image]);

        // Assert
        static::assertSame([1 => true], $first);
        static::assertSame([1 => true], $second);
    }

    public function testEmptyInputShortCircuits(): void
    {
        // Arrange
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItems');
        $cache = $this->cache($pool, requiredLocales: []);

        // Act & Assert
        static::assertSame([], $cache->getMissingAltMap([]));
    }

    /**
     * @param list<string> $requiredLocales
     */
    private function cache(
        CacheItemPoolInterface $pool,
        array $requiredLocales = [],
        ?AltLocaleRequirementResolver $resolver = null,
        ?LoggerInterface $logger = null,
    ): ImageAltStatusCache {
        if ($resolver === null) {
            $resolver = $this->createStub(AltLocaleRequirementResolver::class);
            $resolver->method('getRequiredAltLocalesForImages')->willReturnCallback(
                static function (array $images) use ($requiredLocales): array {
                    $result = [];
                    foreach ($images as $image) {
                        $result[(int) $image->getId()] = $requiredLocales;
                    }

                    return $result;
                },
            );
        }

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredDefaultLocale')->willReturn('en');

        return new ImageAltStatusCache(
            $pool,
            $resolver,
            $language,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private static function imageWithId(int $id): Image
    {
        $image = new Image();
        $property = new ReflectionProperty(Image::class, 'id');
        $property->setValue($image, $id);

        return $image;
    }
}
