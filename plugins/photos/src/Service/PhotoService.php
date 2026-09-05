<?php declare(strict_types=1);

namespace Plugin\Photos\Service;

use App\Comment\CommentService;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Repository\PhotoRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

readonly class PhotoService
{
    public const string ITEM_TYPE = 'photo';

    public function __construct(
        private EntityManagerInterface $em,
        private PhotoRepository $photoRepo,
        private ExifService $exifService,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
        private CommentService $commentService,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private ActionDispatcher $dispatcher,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, array<string, mixed>> $translations language code => [title, description]
     */
    public function create(UploadedFile $file, User $user, array $translations): ?Photo
    {
        $meta = $this->exifService->extract((string) $file->getRealPath());

        try {
            $image = $this->imageService->upload($file, $user, ImageType::PluginPhotosPhoto);
        } catch (Throwable $e) {
            $this->logger->error('Failed to store uploaded photo: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }

        if (!$image instanceof Image) {
            return null;
        }

        $photo = new Photo();
        $photo->setImage($image);
        $photo->setCreatedBy($user->getId());
        $photo->setCreatedAt(new DateTimeImmutable());
        $photo->setMeta($meta);
        $photo->setTakenAt($this->exifService->takenAtOf($meta));
        $this->applyTranslations($photo, $translations);

        $this->em->persist($photo);
        $this->em->flush();

        $this->imageService->createThumbnails($image, ImageType::PluginPhotosPhoto);
        $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginPhotosPhoto, (int) $photo->getId());
        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $photo->getId());

        return $photo;
    }

    /**
     * @param array<string, array<string, mixed>> $translations language code => [title, description]
     */
    public function updateTranslations(Photo $photo, array $translations): void
    {
        $this->applyTranslations($photo, $translations);

        $this->em->persist($photo);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $photo->getId());
    }

    public function delete(Photo $photo): void
    {
        $photoId = (int) $photo->getId();
        $image = $photo->getImage();

        $this->commentService->deleteAllFor(self::ITEM_TYPE, $photoId);

        if ($image instanceof Image) {
            $this->imageLocationService->removeLocation((int) $image->getId(), ImageType::PluginPhotosPhoto, $photoId);
        }

        $this->em->remove($photo);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $photoId);
    }

    public function isOwnedBy(Photo $photo, User $user): bool
    {
        return $photo->getCreatedBy() === $user->getId();
    }

    /** @return Photo[] */
    public function getList(): array
    {
        return $this->photoRepo->findAll($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    /** @return Photo[] */
    public function getManagedList(): array
    {
        return $this->photoRepo->findAll($this->adminItemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    /** @return Photo[] */
    public function getStream(int $userId, ?int $limit = null): array
    {
        return $this->photoRepo->findByCreator($userId, $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE), $limit);
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, list<Photo>> user id => their most recent photos
     */
    public function getStreams(array $userIds, int $limit): array
    {
        $allowedIds = $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE);

        $streams = [];
        foreach ($userIds as $userId) {
            $streams[$userId] = $this->photoRepo->findByCreator($userId, $allowedIds, $limit);
        }

        return $streams;
    }

    /** @return array<int, int> user id => photo count, highest count first */
    public function getStreamAuthors(): array
    {
        return $this->photoRepo->countByCreator($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function hasStream(int $userId): bool
    {
        return $this->getStream($userId, 1) !== [];
    }

    public function get(int $id): ?Photo
    {
        return $this->photoRepo->findOneAllowed($id, $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function getManaged(int $id): ?Photo
    {
        return $this->photoRepo->findOneAllowed($id, $this->adminItemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function getAttached(int $id): ?Photo
    {
        return $this->photoRepo->findOneAllowed($id, null);
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     */
    private function applyTranslations(Photo $photo, array $translations): void
    {
        foreach ($translations as $language => $fields) {
            $title = trim((string) ($fields['title'] ?? ''));
            $translation = $photo->findTranslation($language);

            if ($title === '') {
                if ($translation !== null) {
                    $photo->removeTranslation($translation);
                }

                continue;
            }

            if ($translation === null) {
                $translation = new PhotoTranslation();
                $translation->setLanguage($language);
                $photo->addTranslation($translation);
            }

            $description = trim((string) ($fields['description'] ?? ''));
            $translation->setTitle($title);
            $translation->setDescription($description === '' ? null : $description);
        }
    }
}
