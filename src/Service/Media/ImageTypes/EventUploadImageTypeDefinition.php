<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Filter\Event\EventFilterService;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

final readonly class EventUploadImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private EventFilterService $eventFilter,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::EventUpload;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_event_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id AS image_id, event_id AS location_id FROM image WHERE event_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $event = $image->getEvent();
        if ($event === null) {
            return null;
        }

        return [
            'subject' => $event->getTitle('en'),
            'route' => 'app_admin_event_edit',
            'params' => ['id' => $event->getId()],
        ];
    }

    public function isReportableAt(int $locationId): bool
    {
        return $this->eventFilter->isEventAccessible($locationId);
    }

    protected function sizes(): array
    {
        return [[1024, 768], [350, 263], [210, 140]];
    }
}
