<?php declare(strict_types=1);

namespace Tests\Unit\Service\ImageLocations;

use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageLocations\AbstractImageLocationProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Minimal concrete implementation for testing AbstractImageLocationProvider::sync().
 */
class TestLocationProvider extends AbstractImageLocationProvider
{
    public array $discovered = [];

    public function getType(): ImageType
    {
        return ImageType::EventTeaser;
    }

    public function getEditLink(int $locationId): ?array
    {
        return null;
    }

    public function discoverImageIds(): array
    {
        return $this->discovered;
    }
}

class AbstractImageLocationProviderTest extends TestCase
{
    private function makeProvider(ImageLocationRepository $repo): TestLocationProvider
    {
        return new TestLocationProvider(
            repo: $repo,
            connection: $this->createStub(Connection::class),
        );
    }

    // ---- sync(): DataProvider for insert/delete/no-op scenarios ----

    #[DataProvider('syncProvider')]
    public function testSync(
        array $currentPairs,
        array $discoveredPairs,
        array $expectedToInsert,
        array $expectedToDelete,
    ): void {
        // Arrange
        $repoMock = $this->createMock(ImageLocationRepository::class);
        $repoMock->method('findPairsByType')->willReturn($currentPairs);

        if ($expectedToInsert !== []) {
            $repoMock->expects($this->once())
                ->method('insertForType')
                ->with(ImageType::EventTeaser, $expectedToInsert);
        }
        if ($expectedToInsert === []) {
            $repoMock->expects($this->never())->method('insertForType');
        }

        if ($expectedToDelete !== []) {
            $repoMock->expects($this->once())
                ->method('deleteByTypeAndPairs')
                ->with(ImageType::EventTeaser, $expectedToDelete);
        }
        if ($expectedToDelete === []) {
            $repoMock->expects($this->never())->method('deleteByTypeAndPairs');
        }

        $provider = $this->makeProvider($repoMock);
        $provider->discovered = $discoveredPairs;

        // Act
        $provider->sync();
    }

    public static function syncProvider(): iterable
    {
        yield 'empty discovered, empty current → no inserts, no deletes' => [
            'currentPairs'    => [],
            'discoveredPairs' => [],
            'expectedToInsert' => [],
            'expectedToDelete' => [],
        ];

        yield 'new pair discovered → insert called' => [
            'currentPairs'    => [],
            'discoveredPairs' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToInsert' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToDelete' => [],
        ];

        yield 'existing pair still discovered → no change' => [
            'currentPairs'    => [['imageId' => 1, 'locationId' => 10]],
            'discoveredPairs' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToInsert' => [],
            'expectedToDelete' => [],
        ];

        yield 'pair in DB but not discovered → delete called' => [
            'currentPairs'    => [['imageId' => 2, 'locationId' => 20]],
            'discoveredPairs' => [],
            'expectedToInsert' => [],
            'expectedToDelete' => [['imageId' => 2, 'locationId' => 20]],
        ];

        yield 'mix: 1 new to insert, 1 to delete, 1 unchanged' => [
            'currentPairs'    => [
                ['imageId' => 1, 'locationId' => 10],
                ['imageId' => 2, 'locationId' => 20],
            ],
            'discoveredPairs' => [
                ['imageId' => 1, 'locationId' => 10],
                ['imageId' => 3, 'locationId' => 30],
            ],
            'expectedToInsert' => [['imageId' => 3, 'locationId' => 30]],
            'expectedToDelete' => [['imageId' => 2, 'locationId' => 20]],
        ];
    }
}
