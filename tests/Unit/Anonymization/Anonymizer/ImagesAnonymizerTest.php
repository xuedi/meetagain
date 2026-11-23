<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization\Anonymizer;

use App\Anonymization\Anonymizer\ImagesAnonymizer;
use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use App\Service\Media\ThumbnailRebuildAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\ActionQueueInterface;
use ReflectionProperty;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class ImagesAnonymizerTest extends AnonymizerTestCase
{
    public function testEveryBatchIsReplacedAndOneThumbnailRebuildIsQueued(): void
    {
        // Arrange
        $batch = array_map($this->image(...), range(1, 100));
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo
            ->method('findAfterId')
            ->willReturnMap([
                [null, 100, $batch],
                [100, 100, [$this->image(101)]],
            ]);
        $imageService = $this->createMock(ImageService::class);
        $imageService
            ->expects($this->exactly(2))
            ->method('replaceWithPlaceholders')
            ->willReturnCallback(count(...));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('clear');
        $actionQueue = $this->createMock(ActionQueueInterface::class);
        $actionQueue->expects($this->once())->method('queue')->with(ThumbnailRebuildAction::IDENTIFIER, ['type' => ThumbnailRebuildAction::ALL], null);
        $subject = new ImagesAnonymizer($imageRepo, $imageService, $em, $actionQueue);

        // Act
        $subject->anonymize($this->context());
    }

    private function image(int $id): Image
    {
        $image = new Image();
        new ReflectionProperty(Image::class, 'id')->setValue($image, $id);

        return $image;
    }
}
