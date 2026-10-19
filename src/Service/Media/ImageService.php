<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use ImagickException;
use ImagickPixel;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mime\MimeTypes;

readonly class ImageService
{
    public const array ACCEPTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/heic',
        'image/heif',
    ];

    public const array MIME_ALIASES = [
        'image/jpg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'image/x-png' => 'image/png',
        'image/heic-sequence' => 'image/heic',
        'image/heif-sequence' => 'image/heif',
    ];

    private const int FREE_AXIS_CEILING = 2400;

    public function __construct(
        private ImageRepository $imageRepo,
        private EntityManagerInterface $entityManager,
        private ImageTypeRegistry $imageTypeRegistry,
        private ThumbnailSizeFormat $thumbnailSizeFormat,
        private FilesystemOperator $imageStorage,
        private FilesystemOperator $thumbnailStorage,
        private LoggerInterface $logger,
        private ImageLocationService $imageLocationService,
        private ImageLocationRepository $imageLocationRepo,
        private LockFactory $lockFactory,
    ) {}

    public function findImage(int $id): ?Image
    {
        return $this->imageRepo->find($id);
    }

    /**
     * @return list<Image>
     */
    public function findFiltered(?ImageType $type, ?DateTimeImmutable $since): array
    {
        return $this->imageRepo->findFiltered($type, $since);
    }

    public function countFiltered(?ImageType $type, ?DateTimeImmutable $since): int
    {
        return $this->imageRepo->countFiltered($type, $since);
    }

    /**
     * @return Image[]
     */
    public function findEventUploads(User $uploader, ?int $eventId): array
    {
        return $this->imageRepo->findBy([
            'uploader' => $uploader,
            'event' => $eventId,
            'type' => ImageType::EventUpload,
        ]);
    }

    public function getEventList(User $user): array
    {
        return $this->imageRepo->getEventList($user);
    }

    public function upload(UploadedFile $imageData, User $user, ImageType $type): ?Image
    {
        $hash = sha1($imageData->getContent());
        $image = $this->imageRepo->findOneBy(['hash' => $hash]);
        if ($image !== null) {
            $image->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($image);

            return $image;
        }

        $mimeType = $this->detectMimeType($imageData);
        $extension = $this->detectExtension($imageData, $mimeType);

        $image = new Image();
        $image->setHash($hash);
        $image->setMimeType($mimeType);
        $image->setExtension($extension);
        $image->setType($type);
        $image->setSize($imageData->getSize() ?: 0);
        $image->setCreatedAt(new DateTimeImmutable());
        $image->setUploader($user);

        if ($user->getId() === null) {
            $this->storeSource($imageData, $image);
            $this->entityManager->persist($image);

            return $image;
        }

        $lock = $this->lockFactory->createLock('image.hash.' . $hash);
        $lock->acquire(true);
        try {
            $stored = $this->imageRepo->findOneBy(['hash' => $hash]);
            if ($stored !== null) {
                $stored->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->persist($stored);

                return $stored;
            }

            $this->storeSource($imageData, $image);
            $this->entityManager->persist($image);
            $this->entityManager->flush();
        } finally {
            $lock->release();
        }

        return $image;
    }

    /**
     * @param UploadedFile[] $files
     */
    public function uploadForEvent(Event $event, array $files, User $user): int
    {
        $newImages = [];
        foreach ($files as $uploadedFile) {
            $image = $this->upload($uploadedFile, $user, ImageType::EventUpload);
            $this->entityManager->persist($image);
            $this->entityManager->flush();
            $event->addImage($image);
            $this->createThumbnails($image, ImageType::EventUpload);
            $newImages[] = $image;
        }
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        foreach ($newImages as $image) {
            $this->imageLocationService->addLocation($image->getId(), ImageType::EventUpload, $event->getId());
        }

        return count($files);
    }

    public function removeIfOrphaned(Image $image): bool
    {
        if ($this->imageLocationRepo->findByImageId((int) $image->getId()) !== []) {
            return false;
        }

        $this->entityManager->createQuery('DELETE App\Entity\ImageReport r WHERE r.image = :image')->setParameter('image', $image)->execute();

        $hash = (string) $image->getHash();
        $source = $this->sourceKey($image);
        $this->entityManager->remove($image);
        $this->entityManager->flush();

        $this->imageStorage->delete($source);
        foreach (array_keys($this->imageTypeRegistry->getThumbnailSizeList()) as $token) {
            $this->thumbnailStorage->delete(sprintf('%s_%s.webp', $hash, $token));
        }

        return true;
    }

    public function createThumbnails(Image $image, ?ImageType $imageType = null): int
    {
        $imageType ??= $image->getType();
        $fitMode = $this->imageTypeRegistry->getFitMode($imageType);
        $missing = [];
        foreach ($this->imageTypeRegistry->getThumbnailSizes($imageType) as [$width, $height]) {
            $target = $this->thumbnailKey($image, $width, $height);
            if (!$this->thumbnailStorage->fileExists($target)) {
                $missing[$target] = [$width, $height];
            }
        }
        if ($missing === []) {
            return 0;
        }

        $source = $this->localCopy($image);
        if ($source === null) {
            return 0;
        }

        $cnt = 0;
        try {
            foreach ($missing as $target => [$width, $height]) {
                try {
                    $imagick = new Imagick();
                    $imagick->readImage($source);
                    $imagick->setImageCompressionQuality(90);
                    $imagick->autoOrient();
                    $this->scaleThumbnail($imagick, $width, $height, $fitMode, $target);
                    $imagick->stripImage();
                    $imagick->setFormat('webp');
                    $this->thumbnailStorage->write($target, $imagick->getImageBlob());
                    ++$cnt;
                } catch (ImagickException $e) {
                    $this->logger->error(sprintf("Error creating thumbnail '%s': %s", $target, $e->getMessage()));
                }
            }
        } finally {
            unlink($source);
        }

        return $cnt;
    }

    public function regenerateAllThumbnails(?ImageType $only = null): int
    {
        $typesByImageId = $this->imageLocationRepo->findTypesPerImageId();

        $cnt = 0;
        foreach ($this->imageRepo->findAll() as $image) {
            foreach ($this->usedTypes($image, $typesByImageId) as $type) {
                if ($only !== null && $type !== $only) {
                    continue;
                }
                $cnt += $this->createThumbnails($image, $type);
            }
        }

        return $cnt;
    }

    public function rotateThumbNail(Image $image): void
    {
        $sizes = $this->imageTypeRegistry->getThumbnailSizes($image->getType());
        foreach ($sizes as [$width, $height]) {
            $thumbnail = $this->thumbnailKey($image, $width, $height);

            try {
                $imagick = new Imagick();
                $imagick->readImageBlob($this->thumbnailStorage->read($thumbnail));
                $imagick->rotateImage(new ImagickPixel('white'), 90);
                $imagick->setOption('webp:lossless', 'true');
                $imagick->setFormat('webp');
                $this->thumbnailStorage->write($thumbnail, $imagick->getImageBlob());
            } catch (ImagickException|FilesystemException $e) {
                $this->logger->error(sprintf("Error rotating thumbnail '%s': %s", $thumbnail, $e->getMessage()));
            }
        }

        // DQL UPDATE bypasses a Doctrine lazy-ghost case: persist+flush on a OneToOne proxy
        // never read from before setUpdatedAt nulls every column.
        $this->entityManager
            ->createQuery('UPDATE App\Entity\Image i SET i.updatedAt = :now WHERE i.id = :id')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('id', $image->getId())
            ->execute();
    }

    public function getStatistics(): array
    {
        $thumpFileList = [];
        $sizeListCount = $this->imageTypeRegistry->getThumbnailSizeList();
        foreach ($this->thumbnailFiles() as $file) {
            $thumpFileList[$file] = true;
            $size = $this->sizeTokenOf($file);
            if ($size === null) {
                continue;
            }
            $sizeListCount[$size] = ($sizeListCount[$size] ?? 0) + 1;
        }

        $imageTypes = [];
        foreach ($this->imageRepo->getFileList() as $type) {
            $imageTypes[$type->name] = ($imageTypes[$type->name] ?? 0) + 1;
        }

        $missingThumbnailsCount = 0;
        foreach ($this->requiredSizeTokensByHash() as $hash => $tokens) {
            foreach (array_keys($tokens) as $token) {
                if (isset($thumpFileList[sprintf('%s_%s.webp', $hash, $token)])) {
                    continue;
                }

                ++$missingThumbnailsCount;
            }
        }

        return [
            'imageCount' => $this->imageRepo->count(),
            'imageTypeList' => $imageTypes,
            'thumbnailSizeList' => $sizeListCount,
            'thumbnailCount' => count($thumpFileList),
            'thumbnailObsoleteCount' => count($this->getObsoleteThumbnails()),
            'thumbnailMissingCount' => $missingThumbnailsCount,
        ];
    }

    public function getObsoleteThumbnails(): array
    {
        $tokensByHash = $this->requiredSizeTokensByHash();

        $list = [];
        foreach ($this->thumbnailFiles() as $file) {
            $hash = explode('_', explode('.', $file)[0], 2)[0];
            $token = $this->sizeTokenOf($file);
            if ($token === null || !isset($tokensByHash[$hash][$token])) {
                $list[] = $file;
            }
        }

        return $list;
    }

    public function deleteObsoleteThumbnails(): int
    {
        $cnt = 0;
        foreach ($this->getObsoleteThumbnails() as $file) {
            if (!$this->thumbnailStorage->fileExists($file)) {
                continue;
            }

            $this->thumbnailStorage->delete($file);
            ++$cnt;
        }

        return $cnt;
    }

    private function normaliseMimeType(string $mimeType): string
    {
        $lower = strtolower(trim($mimeType));

        return self::MIME_ALIASES[$lower] ?? $lower;
    }

    public function readOriginal(Image $image): ?string
    {
        try {
            return $this->imageStorage->read($this->sourceKey($image));
        } catch (FilesystemException) {
            return null;
        }
    }

    /**
     * @return array{content: string, mimeType: string}|null null when the original file is missing
     */
    public function renderPreview(Image $image, int $maxEdge = 1024): ?array
    {
        $source = $this->localCopy($image);
        if ($source === null) {
            return null;
        }

        try {
            $imagick = new Imagick();
            $imagick->readImage($source);
            $imagick->setImageCompressionQuality(90);
            $imagick->autoOrient();
            if ($imagick->getImageWidth() > $maxEdge || $imagick->getImageHeight() > $maxEdge) {
                $imagick->thumbnailImage($maxEdge, $maxEdge, true);
            }
            $imagick->stripImage();
            $imagick->setFormat('webp');

            return ['content' => $imagick->getImageBlob(), 'mimeType' => 'image/webp'];
        } catch (ImagickException $e) {
            $this->logger->error(sprintf("Error rendering preview for image '%s': %s", $image->getHash(), $e->getMessage()));

            return ['content' => (string) file_get_contents($source), 'mimeType' => $image->getMimeType()];
        } finally {
            unlink($source);
        }
    }

    public function renderPng(string $content, int $maxHeight): ?string
    {
        if ($content === '') {
            return null;
        }

        try {
            $imagick = new Imagick();
            $imagick->readImageBlob($content);
            $imagick->autoOrient();
            if ($imagick->getImageHeight() > $maxHeight) {
                $imagick->thumbnailImage(self::FREE_AXIS_CEILING, $maxHeight, true);
            }
            $imagick->stripImage();
            $imagick->setImageFormat('png');

            return $imagick->getImageBlob();
        } catch (ImagickException $e) {
            $this->logger->error(sprintf('Error rendering a PNG: %s', $e->getMessage()));

            return null;
        }
    }

    /**
     * @return array<string, array<string, true>> hash => set of valid size tokens
     */
    private function requiredSizeTokensByHash(): array
    {
        $typesByImageId = $this->imageLocationRepo->findTypesPerImageId();

        $map = [];
        foreach ($this->imageRepo->findAll() as $image) {
            $tokens = [];
            foreach ($this->usedTypes($image, $typesByImageId) as $type) {
                foreach ($this->imageTypeRegistry->getThumbnailSizes($type) as [$width, $height]) {
                    $tokens[$this->thumbnailSizeFormat->format($width, $height)] = true;
                }
            }
            $map[$image->getHash()] = $tokens;
        }

        return $map;
    }

    /**
     * @param array<int, list<ImageType>> $typesByImageId
     * @return list<ImageType>
     */
    private function usedTypes(Image $image, array $typesByImageId): array
    {
        return [$image->getType(), ...($typesByImageId[(int) $image->getId()] ?? [])];
    }

    private function scaleThumbnail(Imagick $imagick, int $width, int $height, ImageFitMode $fitMode, string $target): void
    {
        $free = ImageTypeDefinitionInterface::FREE_AXIS;

        if ($width === $free) {
            $imagick->thumbnailImage(self::FREE_AXIS_CEILING, $height, true);
            $this->logFreeAxisClamp($imagick->getImageWidth(), $imagick->getImageHeight(), $height, $target);

            return;
        }

        if ($height === $free) {
            $imagick->thumbnailImage($width, self::FREE_AXIS_CEILING, true);
            $this->logFreeAxisClamp($imagick->getImageHeight(), $imagick->getImageWidth(), $width, $target);

            return;
        }

        if ($fitMode === ImageFitMode::Fit) {
            $imagick->thumbnailImage($width, $height, true);

            return;
        }

        $imagick->cropThumbnailImage($width, $height);
    }

    private function logFreeAxisClamp(int $freeAxis, int $fixedAxis, int $requestedFixedAxis, string $target): void
    {
        if ($freeAxis !== self::FREE_AXIS_CEILING || $fixedAxis >= $requestedFixedAxis) {
            return;
        }

        $this->logger->warning(sprintf(
            "Thumbnail '%s' hit the %dpx free-axis ceiling; its fixed axis is %dpx instead of the requested %dpx.",
            $target,
            self::FREE_AXIS_CEILING,
            $fixedAxis,
            $requestedFixedAxis,
        ));
    }

    private function detectMimeType(UploadedFile $imageData): string
    {
        $serverMime = $imageData->getMimeType();
        if ($serverMime === null || $serverMime === '') {
            throw new RuntimeException('Could not determine MIME type for uploaded file.');
        }

        $mimeType = $this->normaliseMimeType($serverMime);
        if (!in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw new RuntimeException(sprintf('Refusing upload of unsupported image type "%s".', $mimeType));
        }

        return $mimeType;
    }

    private function detectExtension(UploadedFile $imageData, string $mimeType): string
    {
        $serverExt = $imageData->guessExtension();
        if ($serverExt !== null && $serverExt !== '') {
            return $serverExt;
        }

        $mimeExt = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;
        if ($mimeExt !== null) {
            return $mimeExt;
        }

        $clientExt = $imageData->getClientOriginalExtension();
        if ($clientExt !== '') {
            return strtolower($clientExt);
        }

        throw new RuntimeException('Could not determine file extension for uploaded file.');
    }

    private function storeSource(UploadedFile $imageData, Image $image): void
    {
        $stream = fopen($imageData->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }
        try {
            $this->imageStorage->writeStream($this->sourceKey($image), $stream);
        } finally {
            fclose($stream);
        }
    }

    private function sourceKey(Image $image): string
    {
        return $image->getHash() . '.' . $image->getExtension();
    }

    private function thumbnailKey(Image $image, int $width, int $height): string
    {
        return sprintf('%s_%s.webp', $image->getHash(), $this->thumbnailSizeFormat->format($width, $height));
    }

    private function localCopy(Image $image): ?string
    {
        $content = $this->readOriginal($image);
        if ($content === null) {
            $this->logger->error(sprintf("The original of image '%s' is missing from the storage.", $image->getHash()));

            return null;
        }

        $path = sprintf('%s/meetagain-image-%s.%s', sys_get_temp_dir(), bin2hex(random_bytes(8)), $image->getExtension());
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function thumbnailFiles(): array
    {
        $files = [];
        foreach ($this->thumbnailStorage->listContents('', false) as $item) {
            if ($item instanceof FileAttributes && !str_starts_with(basename($item->path()), '.')) {
                $files[] = $item->path();
            }
        }

        return $files;
    }

    private function sizeTokenOf(string $file): ?string
    {
        return explode('_', explode('.', $file)[0], 2)[1] ?? null;
    }
}
