<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;

readonly class SwapReversedVenueCoordinates implements DataHotfixInterface
{
    private const float MAX_LATITUDE = 90.0;

    public function __construct(
        private LocationRepository $repository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_09_21_swap_reversed_venue_coordinates';
    }

    #[Override]
    public function execute(): void
    {
        foreach ($this->repository->findAll() as $venue) {
            $latitude = $venue->getLatitude();
            $longitude = $venue->getLongitude();
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                continue;
            }

            $latitudeFits = abs((float) $latitude) <= self::MAX_LATITUDE;
            $longitudeFitsAsLatitude = abs((float) $longitude) <= self::MAX_LATITUDE;

            if (!$latitudeFits && $longitudeFitsAsLatitude) {
                $venue->setLatitude($longitude);
                $venue->setLongitude($latitude);
                continue;
            }

            $looksReversed = abs((float) $latitude) < abs((float) $longitude);
            if ($latitudeFits && $longitudeFitsAsLatitude && $looksReversed) {
                $this->logger->warning('Venue coordinates may be reversed; check them against the venue city', [
                    'location_id' => $venue->getId(),
                ]);
            }
        }

        $this->em->flush();
    }
}
