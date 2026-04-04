<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

abstract class AbstractImageLocationProvider implements ImageLocationProviderInterface
{
    public function __construct(
        protected readonly ImageLocationRepository $repo,
        protected readonly Connection $connection,
    ) {}

    /**
     * Reads current DB state for this type, diffs against discoverImageIds(),
     * and applies partial delete/insert — never truncates the whole table.
     */
    final public function sync(): void
    {
        $discovered = $this->discoverImageIds();
        $current = $this->repo->findPairsByType($this->getType());

        $discoveredKeys = [];
        foreach ($discovered as $pair) {
            $discoveredKeys[$pair['imageId'] . ':' . $pair['locationId']] = $pair;
        }

        $currentKeys = [];
        foreach ($current as $pair) {
            $currentKeys[$pair['imageId'] . ':' . $pair['locationId']] = $pair;
        }

        $toDelete = array_values(array_diff_key($currentKeys, $discoveredKeys));
        $toInsert = array_values(array_diff_key($discoveredKeys, $currentKeys));

        if ($toDelete !== []) {
            $this->repo->deleteByTypeAndPairs($this->getType(), $toDelete);
        }

        if ($toInsert !== []) {
            $this->repo->insertForType($this->getType(), $toInsert);
        }
    }
}
