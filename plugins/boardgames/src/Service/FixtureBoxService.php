<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\Game;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class FixtureBoxService
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private EntityManagerInterface $em,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
    ) {}

    public function attach(Game $game, ?string $file, User $uploader): ?Image
    {
        if ($file === null) {
            return null;
        }

        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file, null, null, true), $uploader, ImageType::PluginBoardgamesBox);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginBoardgamesBox);

        $game->setBoxImage($image);
        $this->em->persist($game);
        $this->em->flush();

        $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginBoardgamesBox, (int) $game->getId());

        return $image;
    }
}
