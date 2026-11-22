<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\OrphanedImagesAction;
use App\Service\Media\ThumbnailSizeFormat;
use DateTimeImmutable;
use DateTimeZone;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OrphanedImagesActionTest extends TestCase
{
    public function testWithoutTheDeleteFlagItOnlyListsTheOrphans(): void
    {
        // Arrange
        $images = $this->createStub(ImageRepository::class);
        $images->method('countUnlocatedBefore')->willReturn(2);
        $images->method('findUnlocatedBefore')->willReturn([$this->image(4), $this->image(9)]);
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('removeIfOrphaned');

        // Act
        $result = $this->action($imageService, $images, true)->run([]);

        // Assert
        static::assertSame(Status::warning, $result->status);
        static::assertSame('2 image(s) older than 7 days are used nowhere: #4, #9. Run again with delete to remove them.', $result->message);
    }

    public function testTheGracePeriodAndTheActiveTypesBoundTheSearch(): void
    {
        // Arrange
        $images = $this->createMock(ImageRepository::class);
        $images
            ->expects($this->once())
            ->method('countUnlocatedBefore')
            ->with(new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('UTC')), [ImageType::EventUpload])
            ->willReturn(0);

        // Act
        $result = $this->action($this->createStub(ImageService::class), $images, true)->run(['delete' => true]);

        // Assert
        static::assertSame(Status::ok, $result->status);
    }

    public function testWithTheDeleteFlagItDeletesUpToTheCapAndReportsTheRest(): void
    {
        // Arrange
        $images = $this->createMock(ImageRepository::class);
        $images->method('countUnlocatedBefore')->willReturn(OrphanedImagesAction::DELETE_PER_RUN + 1);
        $images
            ->expects($this->once())
            ->method('findUnlocatedBefore')
            ->with(static::anything(), static::anything(), OrphanedImagesAction::DELETE_PER_RUN)
            ->willReturn([$this->image(4), $this->image(9)]);
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->exactly(2))->method('removeIfOrphaned')->willReturn(true);

        // Act
        $result = $this->action($imageService, $images, true)->run(['delete' => true]);

        // Assert
        static::assertSame(Status::warning, $result->status);
        static::assertSame(sprintf('Deleted 2 unused image(s); %d remain, run again to continue.', OrphanedImagesAction::DELETE_PER_RUN - 1), $result->message);
    }

    public function testAFailedDiscoveryDeletesNothing(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('removeIfOrphaned');

        // Act
        $result = $this->action($imageService, $this->createStub(ImageRepository::class), false)->run(['delete' => true]);

        // Assert
        static::assertSame(Status::error, $result->status);
    }

    private function action(ImageService $imageService, ImageRepository $images, bool $discoveryComplete): OrphanedImagesAction
    {
        $locations = $this->createStub(ImageLocationService::class);
        $locations->method('discover')->willReturn($discoveryComplete);
        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('getType')->willReturn(ImageType::EventUpload);

        return new OrphanedImagesAction(
            $imageService,
            $locations,
            $images,
            new ImageTypeRegistry([$definition], new ThumbnailSizeFormat()),
            new MockClock('2026-11-22 12:00:00', 'UTC'),
        );
    }

    private function image(int $id): Image
    {
        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn($id);

        return $image;
    }
}
