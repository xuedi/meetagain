<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use ImagickException;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Twig\Environment;

readonly class ImageService
{
    public function __construct(
        private ImageRepository $imageRepo,
        private EntityManagerInterface $entityManager,
        private ConfigService $configService,
        private ExtendedFilesystem $filesystem,
        private LoggerInterface $logger,
        private Environment $twig,
        private string $kernelProjectDir,
    ) {
    }

    public function upload(UploadedFile $imageData, User $user, ImageType $type): null|Image
    {
        // load or create
        $hash = sha1($imageData->getContent());
        $image = $this->imageRepo->findOneBy(['hash' => $hash]);
        if ($image !== null) {
            $image->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($image);

            return $image;
        }

        $image = new Image();
        $image->setHash($hash);
        $image->setMimeType($imageData->getMimeType());
        $image->setExtension($imageData->guessExtension());
        $image->setType($type);
        $image->setSize($imageData->getSize() ?? 0);
        $image->setCreatedAt(new DateTimeImmutable());
        $image->setUploader($user);

        $this->filesystem->copy($imageData->getRealPath(), $this->getSourceFile($image));
        $this->entityManager->persist($image);

        return $image;
    }

    public function createThumbnails(Image $image, null|ImageType $imageType = null): int
    {
        $cnt = 0;
        $source = $this->getSourceFile($image);
        $imageType ??= $image->getType();
        $sizes = $this->configService->getThumbnailSizes($imageType);
        foreach ($sizes as [$width, $height]) {
            $target = $this->getThumbnailFile($image, $width, $height);
            if ($this->filesystem->fileExists($target)) {
                continue;
            }

            try {
                $imagick = new Imagick();
                $imagick->readImage($source);
                $imagick->setImageCompressionQuality(90);
                $imagick->autoOrient();
                $imagick->cropThumbnailImage($width, $height);
                $imagick->stripImage(); // metadata
                $imagick->setFormat('webp');
                $imagick->writeImage($target);
                $cnt++;
            } catch (ImagickException $e) {
                $this->logger->error(sprintf("Error rotating thumbnail '%s': %s", $target, $e->getMessage()));
                ;
            }
        }

        return $cnt;
    }

    public function rotateThumbNail(Image $image): void
    {
        $sizes = $this->configService->getThumbnailSizes($image->getType());
        foreach ($sizes as [$width, $height]) {
            $thumbnail = $this->getThumbnailFile($image, $width, $height);

            try {
                $imagick = new Imagick();
                $imagick->readImage($thumbnail);
                $imagick->rotateImage(new ImagickPixel('white'), 90);
                $imagick->writeImage($thumbnail);
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            } catch (ImagickException $e) {
                $this->logger->error(sprintf("Error rotating thumbnail '%s': %s", $thumbnail, $e->getMessage()));
                ;
            }
        }
    }

    public function getStatistics(): array
    {
        $thumpFileList = [];
        $sizeListCount = $this->configService->getThumbnailSizeList();
        foreach ($this->filesystem->scanDirectory($this->getThumbnailDir()) as $file) {
            if (str_starts_with((string) $file, '.')) {
                continue;
            }
            $thumpFileList[$file] = true;
            $size = explode('_', explode('.', (string) $file)[0])[1];
            $sizeListCount[$size] = ($sizeListCount[$size] ?? 0) + 1;
        }

        $imageTypes = [];
        $missingThumbnailsCount = 0;
        foreach ($this->imageRepo->getFileList() as $hash => $type) {
            $imageTypes[$type->name] = ($imageTypes[$type->name] ?? 0) + 1;
            foreach ($this->configService->getThumbnailSizes($type) as [$width, $height]) {
                $expected = $this->getThumbnailFileByHash($hash, $width, $height, true);
                if (!isset($thumpFileList[$expected])) {
                    $missingThumbnailsCount++;
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
        $imageList = $this->imageRepo->getFileList();

        $list = [];
        foreach ($this->filesystem->scanDirectory($this->getThumbnailDir()) as $file) {
            if (str_starts_with((string) $file, '.')) {
                continue;
            }
            [$fileName, $fileType] = explode('.', (string) $file);
            [$hash, $size] = explode('_', $fileName);
            [$width, $height] = explode('x', $size);
            if (!isset($imageList[$hash])) {
                $list[] = $file;
                continue;
            }
            if (!$this->configService->isValidThumbnailSize($imageList[$hash], (int) $width, (int) $height)) {
                $list[] = $file;
            }
        }

        return $list;
    }

    public function deleteObsoleteThumbnails(): int
    {
        $cnt = 0;
        foreach ($this->getObsoleteThumbnails() as $file) {
            if ($this->filesystem->exists($this->getThumbnailDir() . $file)) {
                $this->filesystem->remove($this->getThumbnailDir() . $file);
                $cnt++;
            }
        }

        return $cnt;
    }

    public function imageTemplateById(int $id): string
    {
        $image = $this->imageRepo->findOneBy(['id' => $id]);
        return $this->twig->render('_block/image.html.twig', [
            'image' => $image,
            'size' => '50x50',
        ]);
    }

    private function getSourceFile(Image $image): string
    {
        $path = $this->kernelProjectDir . '/data/images/';
        return $path . $image->getHash() . '.' . $image->getExtension();
    }

    private function getThumbnailFile(Image $image, int $width, int $height): string
    {
        return $this->getThumbnailFileByHash($image->getHash(), $width, $height);
    }

    private function getThumbnailFileByHash(string $hash, int $width, int $height, null|bool $justName = false): string
    {
        $filename = sprintf('%s_%sx%s.webp', $hash, $width, $height);
        if ($justName) {
            return $filename;
        }

        return $this->getThumbnailDir() . $filename;
    }

    private function getThumbnailDir(): string
    {
        return $this->kernelProjectDir . '/public/images/thumbnails/';
    }
}
