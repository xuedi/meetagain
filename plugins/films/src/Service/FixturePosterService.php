<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Films\Entity\Film;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class FixturePosterService
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private EntityManagerInterface $em,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
    ) {}

    public function attach(Film $film, ?string $file, User $uploader): ?Image
    {
        if ($file === null) {
            return null;
        }

        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file, null, null, true), $uploader, ImageType::PluginFilmsPoster);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginFilmsPoster);

        $film->setPosterImage($image);
        $this->em->persist($film);
        $this->em->flush();

        $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginFilmsPoster, (int) $film->getId());

        return $image;
    }
}
