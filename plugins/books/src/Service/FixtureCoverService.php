<?php declare(strict_types=1);

namespace Plugin\Books\Service;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Books\Entity\Book;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class FixtureCoverService
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private EntityManagerInterface $em,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
    ) {}

    public function attach(Book $book, ?string $file, User $uploader): ?Image
    {
        if ($file === null) {
            return null;
        }

        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file, null, null, true), $uploader, ImageType::PluginBooksCover);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginBooksCover);

        $book->setCoverImage($image);
        $this->em->persist($book);
        $this->em->flush();

        $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginBooksCover, (int) $book->getId());

        return $image;
    }
}
