<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use App\Service\Media\MetadataStripAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MetadataStripActionTest extends TestCase
{
    private const int STEP = 2;

    public function testAFullBatchContinuesAfterItsLastImageAndCarriesBothCounts(): void
    {
        // Arrange
        $images = $this->createMock(ImageRepository::class);
        $images
            ->expects($this->once())
            ->method('findAfterId')
            ->with(40, self::STEP)
            ->willReturn([$this->image(41), $this->image(52)]);
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('stripOriginalMetadata')->willReturn(true, false);

        // Act
        $result = $this->action($imageService, $images)->run([], '40:5:1');

        // Assert
        static::assertSame(Status::warning, $result->status);
        static::assertSame('52:6:1', $result->continueFrom);
    }

    public function testAShortBatchFinishesAndAnUnreadableOriginalIsCountedNotFatal(): void
    {
        // Arrange
        $images = $this->createMock(ImageRepository::class);
        $images
            ->expects($this->once())
            ->method('findAfterId')
            ->with(null, self::STEP)
            ->willReturn([$this->image(3)]);
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('stripOriginalMetadata')->willThrowException(new RuntimeException('missing'));

        // Act
        $result = $this->action($imageService, $images)->run([]);

        // Assert
        static::assertNull($result->continueFrom);
        static::assertSame(Status::warning, $result->status);
        static::assertSame('Stripped the metadata of 0 original(s); 1 could not be read or were missing.', $result->message);
    }

    public function testACleanRunFinishesOk(): void
    {
        // Arrange
        $images = $this->createStub(ImageRepository::class);
        $images->method('findAfterId')->willReturn([]);

        // Act
        $result = $this->action($this->createStub(ImageService::class), $images)->run([]);

        // Assert
        static::assertSame(Status::ok, $result->status);
    }

    private function action(ImageService $imageService, ImageRepository $images): MetadataStripAction
    {
        return new MetadataStripAction(
            $imageService,
            $images,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            self::STEP,
        );
    }

    private function image(int $id): Image
    {
        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn($id);

        return $image;
    }
}
