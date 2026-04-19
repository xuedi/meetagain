<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\ImageLocationRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocations\ImageLocationProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ImageLocationService
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private CmsBlockRepository $cmsBlockRepository,
        private ImageLocationRepository $locationRepository,
        private LoggerInterface $logger,
        #[AutowireIterator(ImageLocationProviderInterface::class)]
        private iterable $providers,
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
     * Full re-sync via all registered providers.
     * Per-provider failures are caught and logged so one broken provider cannot abort the rest.
     */
    public function discover(): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->sync();
            } catch (\Throwable $e) {
                $this->logger->error('Image location discovery failed for provider {provider}: {message}', [
                    'provider' => $provider::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Returns the edit link for a given ImageLocation by delegating to the matching provider.
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolveEditLink(ImageLocation $location): ?array
    {
        foreach ($this->providers as $provider) {
            if ($provider->getType() === $location->getLocationType()) {
                return $provider->getEditLink($location->getLocationId());
            }
        }

        return null;
    }

    /**
     * Returns location context for an image: a human-readable label, an optional admin route name,
     * and optional route params. Returns null when the location cannot be determined.
     *
     * @return array{label: string, route: string|null, params: array<string, mixed>}|null
     */
    public function locate(Image $image): ?array
    {
        // Event upload — image carries the FK to the event
        if ($image->getEvent() !== null) {
            $event = $image->getEvent();

            return [
                'label' => sprintf('Event upload: %s', $event->getTitle('en')),
                'route' => 'app_admin_event_edit',
                'params' => ['id' => $event->getId()],
            ];
        }

        return match ($image->getType()) {
            ImageType::ProfilePicture => $this->locateProfilePicture($image),
            ImageType::EventTeaser => $this->locateEventTeaser($image),
            ImageType::CmsBlock, ImageType::CmsCardImage, ImageType::CmsGallery => $this->locateCmsBlock($image),
            default => null,
        };
    }

    private function locateProfilePicture(Image $image): ?array
    {
        $user = $this->userRepository->findOneBy(['image' => $image]);
        if ($user === null) {
            return null;
        }

        return [
            'label' => sprintf('Profile picture: %s', $user->getName()),
            'route' => 'app_admin_member_edit',
            'params' => ['id' => $user->getId()],
        ];
    }

    private function locateEventTeaser(Image $image): ?array
    {
        $event = $this->eventRepository->findOneBy(['previewImage' => $image]);
        if ($event === null) {
            return null;
        }

        return [
            'label' => sprintf('Event preview: %s', $event->getTitle('en')),
            'route' => 'app_admin_event_edit',
            'params' => ['id' => $event->getId()],
        ];
    }

    private function locateCmsBlock(Image $image): ?array
    {
        $block = $this->cmsBlockRepository->findOneBy(['image' => $image]);
        if ($block === null || $block->getPage() === null) {
            return null;
        }

        return [
            'label' => sprintf('CMS block on page #%d', $block->getPage()->getId()),
            'route' => 'app_admin_cms_edit',
            'params' => ['id' => $block->getPage()->getId()],
        ];
    }
}
