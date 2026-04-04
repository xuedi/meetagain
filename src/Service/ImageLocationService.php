<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;

readonly class ImageLocationService
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private CmsBlockRepository $cmsBlockRepository,
    ) {}

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
                'label' => sprintf('Event upload: %s', $event->getName()),
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
            'label' => sprintf('Event preview: %s', $event->getName()),
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
