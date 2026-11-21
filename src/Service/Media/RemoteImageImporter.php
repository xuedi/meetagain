<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class RemoteImageImporter
{
    public const int MAX_BYTES = 10_485_760;

    public function __construct(
        #[Autowire(service: 'remote.client')]
        private HttpClientInterface $httpClient,
        private ImageService $imageService,
        private LoggerInterface $logger,
    ) {}

    public function import(string $url, User $user, ImageType $type): ?Image
    {
        $tempFile = sys_get_temp_dir() . '/' . uniqid('remote_', true) . '.jpg';

        try {
            if (!$this->download($url, $tempFile)) {
                return null;
            }

            $image = $this->imageService->upload(new UploadedFile($tempFile, 'remote.jpg', 'image/jpeg', null, true), $user, $type);
            if ($image !== null) {
                $this->imageService->createThumbnails($image, $type);
            }

            return $image;
        } catch (Throwable $e) {
            $this->logger->error('Failed to import remote image: ' . $e->getMessage(), ['url' => $url, 'exception' => $e]);

            return null;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function download(string $url, string $target): bool
    {
        $response = $this->httpClient->request('GET', $url, ['max_redirects' => 3]);
        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Remote image not available', ['url' => $url, 'status' => $response->getStatusCode()]);

            return false;
        }

        $handle = fopen($target, 'wb');
        if ($handle === false) {
            return false;
        }

        $received = 0;
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                $received += strlen($chunk->getContent());
                if ($received > self::MAX_BYTES) {
                    $response->cancel();
                    $this->logger->warning('Remote image exceeds the size cap', ['url' => $url]);

                    return false;
                }
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        return $received > 0;
    }
}
