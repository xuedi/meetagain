<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageService;
use Plugin\Dishes\Entity\Dish;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class FixturePhotoService
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private ImageService $imageService,
        private DishService $dishService,
    ) {}

    public function attach(Dish $dish, ?string $file, User $uploader): ?Image
    {
        if ($file === null) {
            return null;
        }

        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file, null, null, true), $uploader, ImageType::PluginDishesPreview);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginDishesPreview);

        $this->dishService->addGalleryImage($dish, $image);

        return $image;
    }
}
