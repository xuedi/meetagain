<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\User;
use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

// TODO: deduplicate thumbnail generation
readonly class UploadService
{
    public function __construct(
        private ImageRepository $imageRepo,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager,
        private string $kernelProjectDir,
    ) {
    }

    public function upload(UploadedFile $imageData, User $user): ?Image
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
        $image->setSize($imageData->getSize() ?? 0);
        $image->setCreatedAt(new DateTimeImmutable());
        $image->setUploader($user);

        $this->filesystem->copy($imageData->getRealPath(), $this->getSourceFile($image));
        $this->entityManager->persist($image);

        return $image;
    }

    public function createThumbnails(Image $image, array $sizes): void // TODO: make type for size and get from config
    {
        $source = $this->getSourceFile($image);
        foreach ($sizes as [$width, $height]) {
            $target = $this->getThumbnailFile($image, $width, $height);

            $imagick = new Imagick();
            $imagick->readImage($source);
            $imagick->setImageCompressionQuality(90);
            $imagick->cropThumbnailImage($width, $height);
            $imagick->stripImage(); // metadata
            $imagick->writeImage($target);
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
