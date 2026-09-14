<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\User;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class LocationsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $locationRepository,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'locations';
    }

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds], ['id' => 'ASC']) as $event) {
            $location = $event->getLocation();
            if (!$location instanceof Location) {
                continue;
            }

            $locationId = (int) $location->getId();
            if (isset($rows[$locationId])) {
                continue;
            }

            $rows[$locationId] = [
                'ref' => $locationId,
                'title' => $location->getName(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'city' => $location->getCity(),
                'country' => '',
            ];
        }

        return array_values($rows);
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = (string) ($row['title'] ?? '');
            $location = $this->locationRepository->findOneBy(['name' => $title]);
            if ($location instanceof Location) {
                $context->count($this->getKey(), Outcome::Matched);
            } else {
                $location = $this->createLocation($row, $title, $context->getSystemUser());
                $context->count($this->getKey(), Outcome::Created);
            }

            $context->mapRef(Location::class, (int) ($row['ref'] ?? 0), $location);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function createLocation(array $row, string $title, User $owner): Location
    {
        $latitude = $row['latitude'] ?? null;
        $longitude = $row['longitude'] ?? null;

        $location = new Location();
        $location->setName($title !== '' ? $title : 'Unknown');
        $location->setDescription('');
        $location->setStreet('');
        $location->setCity((string) ($row['city'] ?? ''));
        $location->setPostcode('');
        $location->setUser($owner);
        $location->setCreatedAt(new DateTimeImmutable());
        $location->setLatitude($latitude !== null ? (string) $latitude : null);
        $location->setLongitude($longitude !== null ? (string) $longitude : null);

        $this->em->persist($location);

        return $location;
    }
}
