<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Entity;

use App\Entity\Image;
use App\Enum\ImageReportReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;

class PhotoTest extends TestCase
{
    public function testAReportedImageIsHiddenFromTheRenderSurfaces(): void
    {
        // Arrange
        $reported = $this->createStub(Image::class);
        $reported->method('getReported')->willReturn(ImageReportReason::Privacy);
        $photo = new Photo()->setImage($reported);

        // Act + Assert
        static::assertNotNull($photo->getImage());
        static::assertNull($photo->getVisibleImage());
    }

    public function testAnUnreportedImageIsVisible(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getReported')->willReturn(null);
        $photo = new Photo()->setImage($image);

        // Act + Assert
        static::assertSame($image, $photo->getVisibleImage());
    }

    public function testTheTitleFallsBackToAnyStoredLanguage(): void
    {
        // Arrange
        $photo = new Photo();
        $photo->addTranslation(new PhotoTranslation()->setLanguage('de')->setTitle('Hafen'));

        // Act + Assert
        static::assertSame('Hafen', $photo->getTranslatedTitle('en'));
        static::assertSame('', $photo->getTranslatedDescription('en'));
    }

    public function testAPhotoWithoutTranslationsHasNoTitle(): void
    {
        // Act + Assert
        static::assertSame('', new Photo()->getTranslatedTitle('en'));
        static::assertSame('', new Photo()->getAnyTranslatedTitle());
    }

    #[DataProvider('cameraLabels')]
    public function testTheCameraLabelCollapsesARedundantMakePrefix(?array $meta, string $expected): void
    {
        // Arrange
        $photo = new Photo()->setMeta($meta);

        // Act + Assert
        static::assertSame($expected, $photo->getCameraLabel());
    }

    /** @return iterable<string, array{0: array<string, scalar>|null, 1: string}> */
    public static function cameraLabels(): iterable
    {
        yield 'different make and model are joined' => [['make' => 'FUJIFILM', 'model' => 'X-T5'], 'FUJIFILM X-T5'];
        yield 'a model already naming its make stands alone' => [['make' => 'Canon', 'model' => 'Canon EOS R6'], 'Canon EOS R6'];
        yield 'only a make' => [['make' => 'Apple'], 'Apple'];
        yield 'no meta at all' => [null, ''];
    }

    public function testAnEmptyMetaMapIsStoredAsNull(): void
    {
        // Act + Assert
        static::assertNull(new Photo()->setMeta([])->getMeta());
    }
}
