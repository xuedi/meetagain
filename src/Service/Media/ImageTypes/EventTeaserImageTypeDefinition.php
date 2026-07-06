<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\EventRepository;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

final class EventTeaserImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::EventTeaser;
    }

    protected function sizes(): array
    {
        return [[1024, 768], [600, 400], [350, 263], [210, 140]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_event_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT preview_image_id AS image_id, id AS location_id FROM event WHERE preview_image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
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
}
