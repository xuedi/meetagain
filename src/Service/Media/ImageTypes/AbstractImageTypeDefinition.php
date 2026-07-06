<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

abstract class AbstractImageTypeDefinition implements ImageTypeDefinitionInterface
{
    /**
     * The report preview and micro sizes every type carries; appended to each definition's
     * distinctive sizes, so individual definitions never have to repeat them.
     */
    private const array UNIVERSAL_SIZES = [[100, 100], [50, 50]];

    public function __construct(
        protected readonly ImageLocationRepository $repo,
        protected readonly Connection $connection,
    ) {}

    /**
     * This type's distinctive [width, height] pairs, including its own native-aspect 350-width
     * admin-preview entry. The universal report and micro sizes are added by thumbnailSizes().
     *
     * @return array<int, array{0: int, 1: int}>
     */
    abstract protected function sizes(): array;

    final public function thumbnailSizes(): array
    {
        $merged = [];
        foreach ([...$this->sizes(), ...self::UNIVERSAL_SIZES] as [$width, $height]) {
            $merged[$width . 'x' . $height] = [$width, $height];
        }

        return array_values($merged);
    }

    public function fitMode(): ImageFitMode
    {
        return ImageFitMode::Crop;
    }

    public function locate(Image $image): ?array
    {
        return null;
    }

    /**
     * Reads the current DB state for this type, diffs against discoverImageIds(),
     * and applies partial delete/insert - never truncates the whole table.
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
