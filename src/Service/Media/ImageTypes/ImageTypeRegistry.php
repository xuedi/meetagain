<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Resolves an ImageType to its single definition. Fails loudly at construction if two definitions
 * claim the same type, and at lookup if a type has no definition - so thumbnail generation cannot
 * silently skip a type.
 */
class ImageTypeRegistry
{
    /** @var array<int, ImageTypeDefinitionInterface> keyed by ImageType->value */
    private array $definitions;

    /**
     * @param iterable<ImageTypeDefinitionInterface> $definitions
     */
    public function __construct(
        #[AutowireIterator(ImageTypeDefinitionInterface::class)]
        iterable $definitions,
    ) {
        $map = [];
        foreach ($definitions as $definition) {
            $type = $definition->getType();
            if (isset($map[$type->value])) {
                throw new RuntimeException(sprintf(
                    'Duplicate image type definition for "%s": %s and %s.',
                    $type->name,
                    $map[$type->value]::class,
                    $definition::class,
                ));
            }
            $map[$type->value] = $definition;
        }

        $this->definitions = $map;
    }

    public function get(ImageType $type): ImageTypeDefinitionInterface
    {
        return $this->definitions[$type->value]
            ?? throw new RuntimeException(sprintf('No image type definition registered for "%s".', $type->name));
    }

    /** @return list<ImageTypeDefinitionInterface> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /** @return array<int, array{0: int, 1: int}> */
    public function getThumbnailSizes(ImageType $type): array
    {
        return $this->get($type)->thumbnailSizes();
    }

    public function getFitMode(ImageType $type): ImageFitMode
    {
        return $this->get($type)->fitMode();
    }

    /**
     * Returns the 'WxH' string for the universal 350-width admin preview thumbnail.
     */
    public function getAdminPreviewSize(ImageType $type): string
    {
        foreach ($this->get($type)->thumbnailSizes() as [$width, $height]) {
            if ($width === 350) {
                return sprintf('%dx%d', $width, $height);
            }
        }

        throw new RuntimeException(sprintf('No 350-width admin preview thumbnail registered for image type "%s".', $type->name));
    }

    /**
     * The union of every registered type's thumbnail sizes, as a 'WxH' => 0 count map.
     *
     * @return array<string, int>
     */
    public function getThumbnailSizeList(): array
    {
        $list = [];
        foreach ($this->definitions as $definition) {
            foreach ($definition->thumbnailSizes() as [$width, $height]) {
                $list[sprintf('%dx%d', $width, $height)] = 0;
            }
        }

        return $list;
    }

    public function isValidThumbnailSize(ImageType $type, int $checkWidth, int $checkHeight): bool
    {
        foreach ($this->get($type)->thumbnailSizes() as [$width, $height]) {
            if ($checkWidth === $width && $checkHeight === $height) {
                return true;
            }
        }

        return false;
    }
}
