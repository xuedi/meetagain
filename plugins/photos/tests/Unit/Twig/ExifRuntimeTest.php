<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Twig\ExifRuntime;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExifRuntimeTest extends TestCase
{
    public function testFormatsTheWholeCuratedSetInPanelOrder(): void
    {
        // Arrange
        $runtime = $this->runtime();

        // Act
        $rows = $runtime->rows($this->photo([
            'make' => 'FUJIFILM',
            'model' => 'X-T5',
            'lens' => 'XF23mmF1.4 R LM WR',
            'exposureTime' => '1/800',
            'fNumber' => 2.8,
            'iso' => 160,
            'focalLength' => 23.0,
            'focalLength35' => 35,
            'exposureBias' => -0.33,
            'flash' => false,
            'whiteBalance' => 0,
            'meteringMode' => 5,
            'width' => 1800,
            'height' => 1200,
        ]));

        // Assert
        static::assertSame([
            ['label' => 'photos_photo.meta_camera', 'value' => 'FUJIFILM X-T5'],
            ['label' => 'photos_photo.meta_lens', 'value' => 'XF23mmF1.4 R LM WR'],
            ['label' => 'photos_photo.meta_exposure', 'value' => '1/800 s'],
            ['label' => 'photos_photo.meta_aperture', 'value' => 'f/2.8'],
            ['label' => 'photos_photo.meta_iso', 'value' => '160'],
            ['label' => 'photos_photo.meta_focal_length', 'value' => '23 mm'],
            ['label' => 'photos_photo.meta_focal_length_35', 'value' => '35 mm'],
            ['label' => 'photos_photo.meta_exposure_bias', 'value' => '-0.33 EV'],
            ['label' => 'photos_photo.meta_flash', 'value' => 'photos_photo.flash_not_fired'],
            ['label' => 'photos_photo.meta_white_balance', 'value' => 'photos_photo.wb_auto'],
            ['label' => 'photos_photo.meta_metering', 'value' => 'photos_photo.metering_pattern'],
            ['label' => 'photos_photo.meta_dimensions', 'value' => '1800 × 1200'],
        ], $rows);
    }

    #[DataProvider('emptyMeta')]
    public function testYieldsNoRows(?array $meta): void
    {
        // Act + Assert
        static::assertSame([], $this->runtime()->rows($this->photo($meta)));
    }

    /** @return iterable<string, array{0: array<string, scalar>|null}> */
    public static function emptyMeta(): iterable
    {
        yield 'no meta at all' => [null];
        yield 'an empty map' => [[]];
        yield 'only values the panel does not show' => [['takenAt' => '2026-04-18 07:42:11']];
        yield 'unusable values' => [['fNumber' => 'wide open', 'meteringMode' => 99, 'iso' => '']];
    }

    public function testAPhotoThatIsGoneYieldsNoRows(): void
    {
        // Act + Assert
        static::assertSame([], $this->runtime()->rows(null));
    }

    public function testASignedExposureBiasKeepsItsSign(): void
    {
        // Act
        $positive = $this->runtime()->rows($this->photo(['exposureBias' => 0.67]));
        $zero = $this->runtime()->rows($this->photo(['exposureBias' => 0.0]));

        // Assert
        static::assertSame('+0.67 EV', $positive[0]['value']);
        static::assertSame('0 EV', $zero[0]['value']);
    }

    /** @param array<string, scalar>|null $meta */
    private function photo(?array $meta): Photo
    {
        return new Photo()->setMeta($meta);
    }

    private function runtime(): ExifRuntime
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ExifRuntime($translator);
    }
}
