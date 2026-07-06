<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media\ImageTypes;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Minimal concrete definition for exercising the shared abstract behaviour.
 */
class TestImageTypeDefinition extends AbstractImageTypeDefinition
{
    /** @var array<array{imageId: int, locationId: int}> */
    public array $discovered = [];

    /** @var array<int, array{0: int, 1: int}> */
    public array $rawSizes = [[400, 400]];

    public function getType(): ImageType
    {
        return ImageType::EventTeaser;
    }

    protected function sizes(): array
    {
        return $this->rawSizes;
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

class AbstractImageTypeDefinitionTest extends TestCase
{
    private function makeDefinition(?ImageLocationRepository $repo = null): TestImageTypeDefinition
    {
        return new TestImageTypeDefinition(
            repo: $repo ?? $this->createStub(ImageLocationRepository::class),
            connection: $this->createStub(Connection::class),
        );
    }

    // ---- thumbnailSizes(): universal merge ----

    public function testThumbnailSizesAppendsUniversalReportAndMicroSizes(): void
    {
        $definition = $this->makeDefinition();
        $definition->rawSizes = [[400, 400], [350, 350]];

        static::assertSame([[400, 400], [350, 350], [100, 100], [50, 50]], $definition->thumbnailSizes());
    }

    public function testThumbnailSizesDeduplicatesWhenDefinitionAlreadyListsUniversalSizes(): void
    {
        $definition = $this->makeDefinition();
        $definition->rawSizes = [[350, 350], [100, 100]];

        static::assertSame([[350, 350], [100, 100], [50, 50]], $definition->thumbnailSizes());
    }

    // ---- fitMode() / locate(): defaults ----

    public function testFitModeDefaultsToCrop(): void
    {
        static::assertSame(ImageFitMode::Crop, $this->makeDefinition()->fitMode());
    }

    public function testLocateDefaultsToNull(): void
    {
        static::assertNull($this->makeDefinition()->locate($this->createStub(\App\Entity\Image::class)));
    }

    // ---- sync(): insert/delete/no-op ----

    #[DataProvider('syncProvider')]
    public function testSync(array $currentPairs, array $discoveredPairs, array $expectedToInsert, array $expectedToDelete): void
    {
        // Arrange
        $repoMock = $this->createMock(ImageLocationRepository::class);
        $repoMock->method('findPairsByType')->willReturn($currentPairs);

        if ($expectedToInsert !== []) {
            $repoMock->expects($this->once())->method('insertForType')->with(ImageType::EventTeaser, $expectedToInsert);
        }
        if ($expectedToInsert === []) {
            $repoMock->expects($this->never())->method('insertForType');
        }

        if ($expectedToDelete !== []) {
            $repoMock->expects($this->once())->method('deleteByTypeAndPairs')->with(ImageType::EventTeaser, $expectedToDelete);
        }
        if ($expectedToDelete === []) {
            $repoMock->expects($this->never())->method('deleteByTypeAndPairs');
        }

        $definition = $this->makeDefinition($repoMock);
        $definition->discovered = $discoveredPairs;

        // Act
        $definition->sync();
    }

    public static function syncProvider(): iterable
    {
        yield 'empty discovered, empty current -> no inserts, no deletes' => [
            'currentPairs' => [],
            'discoveredPairs' => [],
            'expectedToInsert' => [],
            'expectedToDelete' => [],
        ];

        yield 'new pair discovered -> insert called' => [
            'currentPairs' => [],
            'discoveredPairs' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToInsert' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToDelete' => [],
        ];

        yield 'existing pair still discovered -> no change' => [
            'currentPairs' => [['imageId' => 1, 'locationId' => 10]],
            'discoveredPairs' => [['imageId' => 1, 'locationId' => 10]],
            'expectedToInsert' => [],
            'expectedToDelete' => [],
        ];

        yield 'pair in DB but not discovered -> delete called' => [
            'currentPairs' => [['imageId' => 2, 'locationId' => 20]],
            'discoveredPairs' => [],
            'expectedToInsert' => [],
            'expectedToDelete' => [['imageId' => 2, 'locationId' => 20]],
        ];

        yield 'mix: 1 new to insert, 1 to delete, 1 unchanged' => [
            'currentPairs' => [
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
