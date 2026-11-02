<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\ThumbnailRebuildAction;
use App\Service\Media\ThumbnailSizeFormat;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;

final class ThumbnailRebuildActionTest extends TestCase
{
    private const int STEP = 2;

    public function testTheTypeChoiceOffersEveryRegisteredType(): void
    {
        // Arrange
        $subject = $this->action($this->createStub(ImageService::class));

        // Act
        $parameters = $subject->getParameters();

        // Assert
        static::assertSame(['admin_system_thumbnails.choice_all' => ThumbnailRebuildAction::ALL, 'GroupLogo' => 'GroupLogo'], $parameters[0]->choices);
    }

    public function testAFullBatchContinuesAfterItsLastImageAndCarriesTheCount(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService
            ->expects($this->once())
            ->method('regenerateThumbnailsAfter')
            ->with(40, self::STEP, null)
            ->willReturn(['created' => 3, 'lastImageId' => 52]);

        // Act
        $result = $this->action($imageService)->run(['type' => ThumbnailRebuildAction::ALL], '40:5');

        // Assert
        static::assertSame(Status::ok, $result->status);
        static::assertSame('52:8', $result->continueFrom);
        static::assertSame('Created 8 missing thumbnail(s) across all image types, up to image #52', $result->message);
    }

    public function testTheFirstStepStartsAtTheBeginningAndAShortBatchFinishes(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService
            ->expects($this->once())
            ->method('regenerateThumbnailsAfter')
            ->with(null, self::STEP, ImageType::GroupLogo)
            ->willReturn(['created' => 1, 'lastImageId' => null]);

        // Act
        $result = $this->action($imageService)->run(['type' => 'GroupLogo']);

        // Assert
        static::assertNull($result->continueFrom);
        static::assertSame('Created 1 missing thumbnail(s) for image type GroupLogo.', $result->message);
    }

    public function testAnUnknownTypeFailsWithoutTouchingAnImage(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('regenerateThumbnailsAfter');

        // Act
        $result = $this->action($imageService)->run(['type' => 'NoSuchType']);

        // Assert
        static::assertSame(Status::error, $result->status);
    }

    private function action(ImageService $imageService): ThumbnailRebuildAction
    {
        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('getType')->willReturn(ImageType::GroupLogo);

        return new ThumbnailRebuildAction(
            $imageService,
            $this->createStub(ImageRepository::class),
            new ImageTypeRegistry([$definition], new ThumbnailSizeFormat()),
            self::STEP,
        );
    }
}
