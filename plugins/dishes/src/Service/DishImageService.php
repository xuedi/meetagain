<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\UserRepository;
use App\Service\Media\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

readonly class DishImageService
{
    public function __construct(
        private ImageService $imageService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {}

    public function uploadFromFile(UploadedFile $file, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for dish image upload', ['userId' => $userId]);

            return null;
        }

        try {
            $image = $this->imageService->upload($file, $user, ImageType::PluginDishesPreview);

            if ($image !== null) {
                $this->imageService->createThumbnails($image, ImageType::PluginDishesPreview);
            }

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to store uploaded dish image: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }
}
