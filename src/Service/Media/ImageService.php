<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use ImagickException;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

readonly class ImageService
{
    private const int FREE_AXIS_CEILING = 2400;

    public function __construct(
        private ImageRepository $imageRepo,
        private EntityManagerInterface $entityManager,
        private ImageTypeRegistry $imageTypeRegistry,
        private ThumbnailSizeFormat $thumbnailSizeFormat,
        private ExtendedFilesystem $filesystem,
        private LoggerInterface $logger,
        private string $kernelProjectDir,
        private ImageLocationService $imageLocationService,
        private ImageLocationRepository $imageLocationRepo,
    ) {}

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

        $this->filesystem->copy($imageData->getRealPath(), $this->getSourcePath($image));
        $this->entityManager->persist($image);

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

    public function createThumbnails(Image $image, ?ImageType $imageType = null): int
    {
        return $this->createRenditions($image, $this->renditionsOfType($imageType ?? $image->getType()));
    }

    /**
     * @param array<string, array{0: int, 1: int, 2: ImageFitMode}> $renditions
     */
    private function createRenditions(Image $image, array $renditions): int
    {
        $cnt = 0;
        $source = $this->getSourcePath($image);
        foreach ($renditions as [$width, $height, $fitMode]) {
            $target = $this->getThumbnailFile($image, $width, $height);
            if ($this->filesystem->fileExists($target)) {
                continue;
            }

            try {
                $imagick = new Imagick();
                $imagick->readImage($source);
                $imagick->setImageCompressionQuality(90);
                $imagick->autoOrient();
                $this->scaleThumbnail($imagick, $width, $height, $fitMode, $target);
                $imagick->stripImage();
                $imagick->setFormat('webp');
                $imagick->writeImage($target);
                ++$cnt;
            } catch (ImagickException $e) {
                $this->logger->error(sprintf("Error creating thumbnail '%s': %s", $target, $e->getMessage()));
            }
        }

        return $cnt;
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: ImageFitMode}> size token => width, height, fit mode
     */
    private function renditionsOfType(ImageType $type): array
    {
        $fitMode = $this->imageTypeRegistry->getFitMode($type);

        $renditions = [];
        foreach ($this->imageTypeRegistry->getThumbnailSizes($type) as [$width, $height]) {
            $renditions[$this->thumbnailSizeFormat->format($width, $height)] = [$width, $height, $fitMode];
        }

        return $renditions;
    }

    /**
     * @param list<ImageType> $locationTypes
     * @return array<string, array{0: int, 1: int, 2: ImageFitMode}>
     */
    private function requiredRenditions(Image $image, array $locationTypes): array
    {
        $renditions = [];
        foreach ([$image->getType(), ...$locationTypes] as $type) {
            foreach ($this->renditionsOfType($type) as $token => $rendition) {
                $renditions[$token] ??= $rendition;
            }
        }

        return $renditions;
    }

    /**
     * @return array<string, array<string, array{0: int, 1: int, 2: ImageFitMode}>> hash => renditions
     */
    private function requiredRenditionsByHash(): array
    {
        $typesByImageId = $this->imageLocationRepo->findTypesPerImageId();

        $map = [];
        foreach ($this->imageRepo->findAll() as $image) {
            $map[$image->getHash()] = $this->requiredRenditions($image, $typesByImageId[$image->getId()] ?? []);
        }

        return $map;
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

    public function regenerateAllThumbnails(): int
    {
        $typesByImageId = $this->imageLocationRepo->findTypesPerImageId();

        $cnt = 0;
        foreach ($this->imageRepo->findAll() as $image) {
            $cnt += $this->createRenditions($image, $this->requiredRenditions($image, $typesByImageId[$image->getId()] ?? []));
        }

        return $cnt;
    }

    public function rotateThumbNail(Image $image): void
    {
        $sizes = $this->imageTypeRegistry->getThumbnailSizes($image->getType());
        foreach ($sizes as [$width, $height]) {
            $thumbnail = $this->getThumbnailFile($image, $width, $height);

            try {
                $imagick = new Imagick();
                $imagick->readImage($thumbnail);
                $imagick->rotateImage(new ImagickPixel('white'), 90);
                $imagick->setOption('webp:lossless', 'true');
                $imagick->writeImage($thumbnail);
            } catch (ImagickException $e) {
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
        foreach ($this->filesystem->scanDirectory($this->getThumbnailDir()) as $file) {
            if (str_starts_with((string) $file, '.')) {
                continue;
            }
            $thumpFileList[$file] = true;
            $size = $this->sizeTokenOf((string) $file);
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
        foreach ($this->requiredRenditionsByHash() as $hash => $renditions) {
            foreach (array_keys($renditions) as $token) {
                if (!isset($thumpFileList[sprintf('%s_%s.webp', $hash, $token)])) {
                    ++$missingThumbnailsCount;
                }
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
        $renditionsByHash = $this->requiredRenditionsByHash();

        $list = [];
        foreach ($this->filesystem->scanDirectory($this->getThumbnailDir()) as $file) {
            if (str_starts_with((string) $file, '.')) {
                continue;
            }
            $hash = explode('_', explode('.', (string) $file)[0], 2)[0];
            $token = $this->sizeTokenOf((string) $file);
            if ($token === null || !isset($renditionsByHash[$hash][$token])) {
                $list[] = $file;
            }
        }

        return $list;
    }

    public function deleteObsoleteThumbnails(): int
    {
        $cnt = 0;
        foreach ($this->getObsoleteThumbnails() as $file) {
            if (!$this->filesystem->exists($this->getThumbnailDir() . $file)) {
                continue;
            }

            $this->filesystem->remove($this->getThumbnailDir() . $file);
            ++$cnt;
        }

        return $cnt;
    }

    private function detectMimeType(UploadedFile $imageData): string
    {
        $serverMime = $imageData->getMimeType();
        if ($serverMime !== null && $serverMime !== '') {
            return $serverMime;
        }

        $clientMime = $imageData->getClientMimeType();
        if ($clientMime !== '' && $clientMime !== 'application/octet-stream') {
            return $clientMime;
        }

        throw new RuntimeException('Could not determine MIME type for uploaded file.');
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

    public function getSourcePath(Image $image): string
    {
        $path = $this->kernelProjectDir . '/data/images/';

        return $path . $image->getHash() . '.' . $image->getExtension();
    }

    /**
     * @return array{content: string, mimeType: string}|null null when the original file is missing
     */
    public function renderPreview(Image $image, int $maxEdge = 1024): ?array
    {
        $source = $this->getSourcePath($image);
        if (!$this->filesystem->fileExists($source)) {
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

            return ['content' => (string) $this->filesystem->getFileContents($source), 'mimeType' => $image->getMimeType()];
        }
    }

    private function getThumbnailFile(Image $image, int $width, int $height): string
    {
        return $this->getThumbnailDir() . sprintf('%s_%s.webp', $image->getHash(), $this->thumbnailSizeFormat->format($width, $height));
    }

    private function sizeTokenOf(string $file): ?string
    {
        return explode('_', explode('.', $file)[0], 2)[1] ?? null;
    }

    private function getThumbnailDir(): string
    {
        return $this->kernelProjectDir . '/public/images/thumbnails/';
    }
}
