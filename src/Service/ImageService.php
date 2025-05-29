<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use ImagickException;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class ImageService
{
    public function __construct(
        private ImageRepository $imageRepo,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager,
        private ConfigService $configService,
        private LoggerInterface $logger,
        private string $kernelProjectDir,
    )
    {
    }

    public function upload(UploadedFile $imageData, User $user, ImageType $type): ?Image
    {
        // load or create
        $hash = sha1($imageData->getContent());
        $image = $this->imageRepo->findOneBy(['hash' => $hash]);
        if ($image !== null) {
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

    public function createThumbnails(Image $image): void
    {
        $source = $this->getSourceFile($image);
        $sizes = $this->configService->getThumbnailSizes($image->getType());
        foreach ($sizes as [$width, $height]) {
            $target = $this->getThumbnailFile($image, $width, $height);
            if (file_exists($target)) {
                continue;
            }

            try {
                $imagick = new Imagick();
                $imagick->readImage($source);
                $imagick->setImageCompressionQuality(90);
                $imagick->autoOrient();
                $imagick->cropThumbnailImage($width, $height);
                $imagick->stripImage(); // metadata
                $imagick->writeImage($target);
            } catch (ImagickException $e) {
                $this->logger->error(sprintf("Error rotating thumbnail '%s': %s", $target, $e->getMessage()));;
            }
        }
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
                $this->logger->error(sprintf("Error rotating thumbnail '%s': %s", $thumbnail, $e->getMessage()));;
            }
        }
    }

    private function getSourceFile(Image $image): string
    {
        $path = $this->kernelProjectDir . '/data/images/';
        return $path . $image->getHash() . '.' . $image->getExtension();
    }

    private function getThumbnailFile(Image $image, int $width, int $height): string
    {
        $path = $this->kernelProjectDir . '/public/images/thumbnails/';
        return $path . $image->getId() . '_' . $width . 'x' . $height . '.' . $image->getExtension(); // TODO: sprintf
    }
}
