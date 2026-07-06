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
        private LoggerInterface $logger,
    ) {}

    /**
     * Insert one location row. No-op if the row already exists (INSERT IGNORE).
     */
    public function addLocation(int $imageId, ImageType $type, int $locationId): void
    {
        $this->locationRepository->insertForType($type, [['imageId' => $imageId, 'locationId' => $locationId]]);
    }

    /**
     * Delete one location row. No-op if the row does not exist.
     */
    public function removeLocation(int $imageId, ImageType $type, int $locationId): void
    {
        $this->locationRepository->deleteByTypeAndPairs($type, [['imageId' => $imageId, 'locationId' => $locationId]]);
    }

    /**
     * Full re-sync via all registered definitions.
     * Per-definition failures are caught and logged so one broken definition cannot abort the rest.
     */
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
    }

    /**
     * Returns the edit link for a given ImageLocation by delegating to its type definition.
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolveEditLink(ImageLocation $location): ?array
    {
        return $this->registry->get($location->getLocationType())->getEditLink($location->getLocationId());
    }

    /**
     * Returns location context for an image: a human-readable label, an optional admin route name,
     * and optional route params. Returns null when the location cannot be determined.
     *
     * @return array{label: string, route: string|null, params: array<string, mixed>}|null
     */
    public function locate(Image $image): ?array
    {
        return $this->registry->get($image->getType())->locate($image);
    }
}
