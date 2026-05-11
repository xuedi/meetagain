<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media\ImageLocations;

use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageLocations\SiteLogoLocationProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SiteLogoLocationProviderTest extends TestCase
{
    public function testGetTypeIsSiteLogo(): void
    {
        $provider = $this->createProvider($this->createStub(Connection::class));

        static::assertSame(ImageType::SiteLogo, $provider->getType());
    }

    public function testGetEditLinkPointsToThemeAdmin(): void
    {
        $provider = $this->createProvider($this->createStub(Connection::class));

        static::assertSame(['route' => 'app_admin_system_theme', 'params' => []], $provider->getEditLink(0));
    }

    /**
     * @param array<string, string>|false $row
     * @param list<array{imageId: int, locationId: int}> $expected
     */
    #[DataProvider('provideDiscoverCases')]
    public function testDiscoverImageIdsHandlesAllPaths(array|false $row, array $expected): void
    {
        // Arrange
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);

        $provider = $this->createProvider($connection);

        // Act / Assert
        static::assertSame($expected, $provider->discoverImageIds());
    }

    public static function provideDiscoverCases(): iterable
    {
        yield 'no row returns empty list' => [false, []];
        yield 'zero value returns empty list' => [['value' => '0'], []];
        yield 'negative value returns empty list' => [['value' => '-5'], []];
        yield 'positive value yields single pair' => [
            ['value' => '42'],
            [['imageId' => 42, 'locationId' => 0]],
        ];
    }

    private function createProvider(Connection $connection): SiteLogoLocationProvider
    {
        return new SiteLogoLocationProvider(
            $this->createStub(ImageLocationRepository::class),
            $connection,
        );
    }
}
