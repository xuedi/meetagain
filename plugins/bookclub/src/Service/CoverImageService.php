<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class CoverImageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ImageService $imageService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private string $kernelProjectDir,
    ) {}

    public function downloadAndSave(string $url, int $userId): ?Image
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->logger->error('User not found for cover image download', ['userId' => $userId]);
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Cover image not available', ['url' => $url, 'status' => $response->getStatusCode()]);
                return null;
            }

            $content = $response->getContent();
            if (empty($content)) {
                return null;
            }

            $tempDir = $this->kernelProjectDir . '/var/tmp/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFile = $tempDir . uniqid('cover_') . '.jpg';
            file_put_contents($tempFile, $content);

            $uploadedFile = new UploadedFile(
                $tempFile,
                'cover.jpg',
                'image/jpeg',
                null,
                true
            );

            $image = $this->imageService->upload(
                $uploadedFile,
                $user,
                ImageType::PluginBookclubCover
            );

            if ($image !== null) {
                $this->imageService->createThumbnails($image);
            }

            @unlink($tempFile);

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to download cover image: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e,
            ]);
            return null;
        }
    }
}
