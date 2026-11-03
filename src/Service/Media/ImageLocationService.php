<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

readonly class ImageLocationService
{
    public function __construct(
        private ImageLocationRepository $locationRepository,
        private ImageTypeRegistry $registry,
        private ImageAltStatusCache $imageAltStatusCache,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return array<int, int>
     */
    public function countPerImageId(): array
    {
        return $this->locationRepository->countPerImageId();
    }

    /**
     * @return ImageLocation[]
     */
    public function findByImageId(int $imageId): array
    {
        return $this->locationRepository->findByImageId($imageId);
    }

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
        $location = $this->registry->get($image->getType())->locate($image);
        if ($location === null) {
            return null;
        }

        $type = $this->translator->trans('image_type.' . strtolower($image->getType()->name));
        $subject = (string) $location['subject'];

        return [
            'label' => $subject === '' ? $type : $this->translator->trans('image_type.location_label', ['%type%' => $type, '%subject%' => $subject]),
            'route' => $location['route'],
            'params' => $location['params'],
        ];
    }
}
