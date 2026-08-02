<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Service;

use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Repository\CommentRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Security\ContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Repository\PhotoRepository;
use Plugin\Photos\Service\ExifService;
use Plugin\Photos\Service\PhotoService;
use Psr\Log\NullLogger;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoServiceTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../fixtures/with-exif.jpg';

    public function testCreateStoresTheExtractedMetaAndTakenAtStamp(): void
    {
        // Arrange
        $service = $this->service();

        // Act
        $photo = $service->create($this->upload(), $this->user(7), ['en' => ['title' => 'Harbour', 'description' => 'At dawn.']]);

        // Assert
        static::assertSame('FUJIFILM', $photo?->getMeta()['make']);
        static::assertSame('2026-04-18 07:42:11', $photo?->getTakenAt()?->format('Y-m-d H:i:s'));
        static::assertSame(7, $photo?->getCreatedBy());
        static::assertSame('Harbour', $photo?->getTranslatedTitle('en'));
        static::assertSame('At dawn.', $photo?->getTranslatedDescription('en'));
    }

    public function testCreateRegistersTheImageLocationAndDispatchesTheCreatedAction(): void
    {
        // Arrange
        $locations = $this->createMock(ImageLocationService::class);
        $locations->expects(static::once())->method('addLocation')->with(0, ImageType::PluginPhotosPhoto, 0);
        $dispatcher = $this->createMock(ActionDispatcher::class);
        $dispatcher->expects(static::once())->method('dispatch')->with(ItemAction::Created, 'photo', 0);
        $service = $this->service(locations: $locations, dispatcher: $dispatcher);

        // Act
        $service->create($this->upload(), $this->user(7), []);
    }

    public function testCreateReturnsNullWhenTheImageCouldNotBeStored(): void
    {
        // Arrange
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('upload')->willReturn(null);
        $service = $this->service(imageService: $imageService);

        // Act
        $photo = $service->create($this->upload(), $this->user(7), []);

        // Assert
        static::assertNull($photo);
    }

    public function testATranslationRowExistsOnlyForALanguageWithATitle(): void
    {
        // Arrange
        $service = $this->service();

        // Act
        $photo = $service->create($this->upload(), $this->user(7), [
            'en' => ['title' => 'Harbour', 'description' => ''],
            'de' => ['title' => '   ', 'description' => 'Ignoriert'],
        ]);

        // Assert
        static::assertCount(1, $photo?->getTranslations() ?? []);
        static::assertNull($photo?->findTranslation('en')?->getDescription());
        static::assertNull($photo?->findTranslation('de'));
    }

    public function testUpdateTranslationsDropsALanguageWhoseTitleWasCleared(): void
    {
        // Arrange
        $service = $this->service();
        $photo = $service->create($this->upload(), $this->user(7), ['en' => ['title' => 'Harbour'], 'de' => ['title' => 'Hafen']]);
        static::assertNotNull($photo);

        // Act
        $service->updateTranslations($photo, ['en' => ['title' => 'Harbour at dawn'], 'de' => ['title' => '']]);

        // Assert
        static::assertSame('Harbour at dawn', $photo->getTranslatedTitle('en'));
        static::assertNull($photo->findTranslation('de'));
    }

    public function testDeleteClearsTheCommentsTheLocationAndDispatchesTheDeletedAction(): void
    {
        // Arrange
        $comments = $this->createMock(CommentRepository::class);
        $comments->expects(static::once())->method('deleteForTarget')->with('photo', 0)->willReturn(0);
        $locations = $this->createMock(ImageLocationService::class);
        $locations->expects(static::once())->method('removeLocation')->with(0, ImageType::PluginPhotosPhoto, 0);
        $dispatcher = $this->createMock(ActionDispatcher::class);
        $dispatcher->expects(static::once())->method('dispatch')->with(ItemAction::Deleted, 'photo', 0);
        $service = $this->service(comments: $comments, locations: $locations, dispatcher: $dispatcher);

        $photo = new Photo();
        $photo->setImage($this->createStub(Image::class));

        // Act
        $service->delete($photo);
    }

    public function testGetListNarrowsThroughTheFrontendFilterChain(): void
    {
        // Arrange
        $filter = $this->createStub(FilterService::class);
        $filter->method('getAllowedItemIds')->willReturn([4, 9]);
        $repository = $this->createMock(PhotoRepository::class);
        $repository->expects(static::once())->method('findAll')->with([4, 9])->willReturn([]);
        $service = $this->service(repository: $repository, filter: $filter);

        // Act
        $service->getList();
    }

    public function testIsOwnedByComparesTheUploaderId(): void
    {
        // Arrange
        $service = $this->service();
        $photo = new Photo();
        $photo->setCreatedBy(7);

        // Act + Assert
        static::assertTrue($service->isOwnedBy($photo, $this->user(7)));
        static::assertFalse($service->isOwnedBy($photo, $this->user(8)));
    }

    private function service(
        ?PhotoRepository $repository = null,
        ?ImageService $imageService = null,
        ?ImageLocationService $locations = null,
        ?CommentRepository $comments = null,
        ?FilterService $filter = null,
        ?ActionDispatcher $dispatcher = null,
    ): PhotoService {
        $imageService ??= $this->imageService();

        return new PhotoService(
            $this->createStub(EntityManagerInterface::class),
            $repository ?? $this->createStub(PhotoRepository::class),
            new ExifService(new NullLogger()),
            $imageService,
            $locations ?? $this->createStub(ImageLocationService::class),
            $this->commentService($comments),
            $filter ?? $this->createStub(FilterService::class),
            $this->createStub(AdminFilterService::class),
            $dispatcher ?? $this->createStub(ActionDispatcher::class),
            new NullLogger(),
        );
    }

    private function commentService(?CommentRepository $comments): CommentService
    {
        $repository = $comments ?? $this->createStub(CommentRepository::class);
        $sanitizer = new ContentSanitizer($this->createStub(HtmlSanitizerInterface::class), $this->createStub(HtmlSanitizerInterface::class));

        return new CommentService($repository, $this->createStub(EntityManagerInterface::class), $sanitizer, new TargetRegistry([]));
    }

    private function imageService(): ImageService
    {
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('upload')->willReturn($this->createStub(Image::class));

        return $imageService;
    }

    private function upload(): UploadedFile
    {
        return new UploadedFile(self::FIXTURE, 'with-exif.jpg', null, null, true);
    }

    private function user(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
