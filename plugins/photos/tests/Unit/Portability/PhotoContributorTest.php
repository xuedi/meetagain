<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Portability\PhotoContributor;
use Plugin\Photos\Repository\PhotoRepository;
use ReflectionProperty;

class PhotoContributorTest extends TestCase
{
    private const array META = ['make' => 'FUJIFILM', 'model' => 'X-T5', 'iso' => 160];

    public function testExportsTheTextsTheMetaTheStampTheImageFileAndTheUploader(): void
    {
        // Arrange
        $repository = $this->createStub(PhotoRepository::class);
        $repository->method('findBy')->willReturn([$this->photo()]);
        $writer = $this->createStub(ImageWriterInterface::class);
        $writer->method('addImage')->willReturn('images/photos/0/photo.jpg');
        $users = $this->createStub(UserRepository::class);
        $users->method('findBy')->willReturn([$this->user(7, 'lena@example.org')]);

        // Act
        $rows = $this->contributor($repository, users: $users)->exportItems([12], $writer);

        // Assert
        static::assertSame(
            [[
                'ref' => 0,
                'translations' => ['en' => ['title' => 'Harbour', 'description' => 'At dawn.']],
                'meta' => self::META,
                'taken_at' => '2026-04-18 07:42:11',
                'image' => 'images/photos/0/photo.jpg',
                'uploader_email' => 'lena@example.org',
                'contest_submitted' => true,
            ]],
            $rows,
        );
    }

    public function testAPhotoWhoseImageCannotBeWrittenIsNotExported(): void
    {
        // Arrange
        $repository = $this->createStub(PhotoRepository::class);
        $repository->method('findBy')->willReturn([$this->photo()]);
        $writer = $this->createStub(ImageWriterInterface::class);
        $writer->method('addImage')->willReturn(null);

        // Act
        $rows = $this->contributor($repository)->exportItems([12], $writer);

        // Assert
        static::assertSame([], $rows);
    }

    public function testTheUploaderOfEachPhotoIsItsCreator(): void
    {
        // Arrange
        $first = $this->photo();
        new ReflectionProperty(Photo::class, 'id')->setValue($first, 12);
        $second = $this->photo();
        new ReflectionProperty(Photo::class, 'id')->setValue($second, 13);
        $second->setCreatedBy(9);
        $repository = $this->createStub(PhotoRepository::class);
        $repository->method('findBy')->willReturn([$first, $second]);

        // Act
        $uploaders = $this->contributor($repository)->getUploaderIds([12, 13]);

        // Assert
        static::assertSame([12 => 7, 13 => 9], $uploaders);
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
        static::assertFalse($photo->isContestSubmitted());
    }

    public function testImportCreditsTheArchivedUploaderAndKeepsTheContestFlag(): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $context = $this->context();
        $context->method('resolveRef')->willReturnCallback(fn(string $class, mixed $ref): ?User => $ref === 'lena@example.org'
            ? $this->user(42, 'lena@example.org')
            : null);

        // Act
        $this->contributor(em: $em)->importItems([[
            'ref' => 12,
            'image' => 'images/photos/12/photo.jpg',
            'uploader_email' => 'lena@example.org',
            'contest_submitted' => true,
        ]], $context);

        // Assert
        $photo = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof Photo))[0];
        static::assertSame(42, $photo->getCreatedBy());
        static::assertTrue($photo->isContestSubmitted());
    }

    public function testAnUnknownUploaderFallsBackToTheImportUser(): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        // Act
        $this->contributor(em: $em)->importItems([['ref' => 12, 'image' => 'photo.jpg', 'uploader_email' => 'gone@example.org']], $this->context());

        // Assert
        $photo = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof Photo))[0];
        static::assertSame(3, $photo->getCreatedBy());
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
        ?UserRepository $users = null,
    ): PhotoContributor {
        return new PhotoContributor(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repository ?? $this->createStub(PhotoRepository::class),
            $locations ?? $this->createStub(ImageLocationService::class),
            $users ?? $this->createStub(UserRepository::class),
        );
    }

    private function context(): ImportContext
    {
        $context = $this->createStub(ImportContext::class);
        $context->method('importImage')->willReturn($this->createStub(Image::class));
        $context->method('getSystemUser')->willReturn($this->user(3, 'import@example.com'));

        return $context;
    }

    private function user(int $id, string $email): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);
        $user->setEmail($email);

        return $user;
    }

    private function photo(): Photo
    {
        $photo = new Photo();
        $photo->setImage($this->createStub(Image::class));
        $photo->setCreatedAt(new DateTimeImmutable('2026-04-19 09:00:00'));
        $photo->setCreatedBy(7);
        $photo->setMeta(self::META);
        $photo->setTakenAt(new DateTimeImmutable('2026-04-18 07:42:11'));
        $photo->setContestSubmitted(true);
        $photo->addTranslation(
            new PhotoTranslation()
                ->setLanguage('en')
                ->setTitle('Harbour')
                ->setDescription('At dawn.'),
        );

        return $photo;
    }
}
