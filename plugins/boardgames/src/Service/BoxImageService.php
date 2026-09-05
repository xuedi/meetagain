<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\UserRepository;
use App\Service\Media\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class BoxImageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ImageService $imageService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private string $kernelProjectDir,
    ) {}

    public function uploadFromFile(UploadedFile $file, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for box image upload', ['userId' => $userId]);

            return null;
        }

        try {
            $image = $this->imageService->upload($file, $user, ImageType::PluginBoardgamesBox);

            if ($image !== null) {
                $this->imageService->createThumbnails($image, ImageType::PluginBoardgamesBox);
            }

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to store uploaded box image: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    public function downloadAndSave(string $url, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for box image download', ['userId' => $userId]);

            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Box image not available', [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            $content = $response->getContent();
            if ($content === '') {
                return null;
            }

            $tempDir = $this->kernelProjectDir . '/var/tmp/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0o755, true);
            }

            $tempFile = $tempDir . uniqid('box_') . '.jpg';
            file_put_contents($tempFile, $content);

            $uploadedFile = new UploadedFile($tempFile, 'box.jpg', 'image/jpeg', null, true);

            $image = $this->imageService->upload($uploadedFile, $user, ImageType::PluginBoardgamesBox);

            if ($image !== null) {
                $this->imageService->createThumbnails($image, ImageType::PluginBoardgamesBox);
            }

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to download box image: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
