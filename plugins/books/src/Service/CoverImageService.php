<?php declare(strict_types=1);

namespace Plugin\Books\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\UserRepository;
use App\Service\Media\ImageService;
use App\Service\Media\RemoteImageImporter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

readonly class CoverImageService
{
    public function __construct(
        private RemoteImageImporter $remoteImageImporter,
        private ImageService $imageService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {}

    public function uploadFromFile(UploadedFile $file, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for cover image upload', ['userId' => $userId]);

            return null;
        }

        try {
            $image = $this->imageService->upload($file, $user, ImageType::PluginBooksCover);

            if ($image !== null) {
                $this->imageService->createThumbnails($image, ImageType::PluginBooksCover);
            }

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to store uploaded cover image: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    public function downloadAndSave(string $url, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for cover image download', ['userId' => $userId]);

            return null;
        }

        return $this->remoteImageImporter->import($url, $user, ImageType::PluginBooksCover);
    }
}
