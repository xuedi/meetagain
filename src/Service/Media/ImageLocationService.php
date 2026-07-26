<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ImageLocationService
{
    public function __construct(
        private ImageLocationRepository $locationRepository,
        private ImageTypeRegistry $registry,
        private ImageAltStatusCache $imageAltStatusCache,
        private LoggerInterface $logger,
    ) {}

    public function addLocation(int $imageId, ImageType $type, int $locationId): void
    {
        $this->locationRepository->insertForType($type, [['imageId' => $imageId, 'locationId' => $locationId]]);
        $this->imageAltStatusCache->invalidateImage($imageId);
    }

    public function removeLocation(int $imageId, ImageType $type, int $locationId): void
    {
        $this->locationRepository->deleteByTypeAndPairs($type, [['imageId' => $imageId, 'locationId' => $locationId]]);
        $this->imageAltStatusCache->invalidateImage($imageId);
    }

    public function discover(): void
    {
        foreach ($this->registry->all() as $definition) {
            try {
                $definition->sync();
            } catch (Throwable $e) {
                $this->logger->error('Image location discovery failed for definition {definition}: {message}', [
                    'definition' => $definition::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        $this->imageAltStatusCache->invalidateAll();
    }

    /**
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolveEditLink(ImageLocation $location): ?array
    {
        return $this->registry->get($location->getLocationType())->getEditLink($location->getLocationId());
    }

    /**
     * @return array{label: string, route: string|null, params: array<string, mixed>}|null
     */
    public function locate(Image $image): ?array
    {
        return $this->registry->get($image->getType())->locate($image);
    }
}
