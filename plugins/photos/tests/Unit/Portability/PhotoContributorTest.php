<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Item\Portability\ImportContext;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Portability\PhotoContributor;
use Plugin\Photos\Repository\PhotoRepository;

class PhotoContributorTest extends TestCase
{
    private const array META = ['make' => 'FUJIFILM', 'model' => 'X-T5', 'iso' => 160];

    public function testExportsTheTextsTheMetaTheStampAndTheImageFile(): void
    {
        // Arrange
        $repository = $this->createStub(PhotoRepository::class);
        $repository->method('findBy')->willReturn([$this->photo()]);
        $writer = $this->createStub(PortableImageWriterInterface::class);
        $writer->method('addImage')->willReturn('images/photos/0/photo.jpg');

        // Act
        $rows = $this->contributor($repository)->exportItems([12], $writer);

        // Assert
        static::assertSame([[
            'ref' => 0,
            'translations' => ['en' => ['title' => 'Harbour', 'description' => 'At dawn.']],
            'meta' => self::META,
            'taken_at' => '2026-04-18 07:42:11',
            'image' => 'images/photos/0/photo.jpg',
        ]], $rows);
    }

    public function testAPhotoWhoseImageCannotBeWrittenIsNotExported(): void
    {
        // Arrange
        $repository = $this->createStub(PhotoRepository::class);
        $repository->method('findBy')->willReturn([$this->photo()]);
        $writer = $this->createStub(PortableImageWriterInterface::class);
        $writer->method('addImage')->willReturn(null);

        // Act
        $rows = $this->contributor($repository)->exportItems([12], $writer);

        // Assert
        static::assertSame([], $rows);
    }

    public function testImportRebuildsTheRowWithoutReExtractingTheFile(): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        // Act
        $result = $this->contributor(em: $em)->importItems([[
            'ref' => 12,
            'translations' => ['en' => ['title' => 'Harbour', 'description' => 'At dawn.'], 'de' => ['title' => 'Hafen', 'description' => null]],
            'meta' => self::META,
            'taken_at' => '2026-04-18 07:42:11',
            'image' => 'images/photos/12/photo.jpg',
        ]], $this->context());

        // Assert
        $photo = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof Photo))[0];
        static::assertSame(1, $result->created);
        static::assertSame(0, $result->matched);
        static::assertSame([12 => 0], $result->refToItemId);
        static::assertSame(self::META, $photo->getMeta());
        static::assertSame('2026-04-18 07:42:11', $photo->getTakenAt()?->format('Y-m-d H:i:s'));
        static::assertSame('Harbour', $photo->getTranslatedTitle('en'));
        static::assertSame('Hafen', $photo->getTranslatedTitle('de'));
        static::assertNull($photo->findTranslation('de')?->getDescription());
    }

    public function testARowWithoutAnImportableImageIsSkipped(): void
    {
        // Arrange
        $context = $this->createStub(ImportContext::class);
        $context->method('importImage')->willReturn(null);

        // Act
        $result = $this->contributor()->importItems([['ref' => 12, 'image' => 'missing.jpg']], $context);

        // Assert
        static::assertSame(0, $result->created);
        static::assertSame([], $result->refToItemId);
    }

    public function testImportRegistersTheImageLocationAfterFlush(): void
    {
        // Arrange
        $locations = $this->createMock(ImageLocationService::class);
        $locations->expects(static::once())->method('addLocation');

        // Act
        $this->contributor(locations: $locations)->importItems([['ref' => 12, 'image' => 'photo.jpg']], $this->context());
    }

    private function contributor(
        ?PhotoRepository $repository = null,
        ?EntityManagerInterface $em = null,
        ?ImageLocationService $locations = null,
    ): PhotoContributor {
        return new PhotoContributor(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repository ?? $this->createStub(PhotoRepository::class),
            $locations ?? $this->createStub(ImageLocationService::class),
        );
    }

    private function context(): ImportContext
    {
        $context = $this->createStub(ImportContext::class);
        $context->method('importImage')->willReturn($this->createStub(Image::class));
        $context->method('getSystemUser')->willReturn($this->createStub(User::class));

        return $context;
    }

    private function photo(): Photo
    {
        $photo = new Photo();
        $photo->setImage($this->createStub(Image::class));
        $photo->setCreatedAt(new DateTimeImmutable('2026-04-19 09:00:00'));
        $photo->setCreatedBy(7);
        $photo->setMeta(self::META);
        $photo->setTakenAt(new DateTimeImmutable('2026-04-18 07:42:11'));
        $photo->addTranslation(new PhotoTranslation()->setLanguage('en')->setTitle('Harbour')->setDescription('At dawn.'));

        return $photo;
    }
}
