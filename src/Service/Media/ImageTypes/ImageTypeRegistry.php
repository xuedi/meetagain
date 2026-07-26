<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Service\Media\ThumbnailSizeFormat;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ImageTypeRegistry
{
    /** @var array<int, ImageTypeDefinitionInterface> keyed by ImageType->value */
    private array $definitions;

    /**
     * @param iterable<ImageTypeDefinitionInterface> $definitions
     */
    public function __construct(
        #[AutowireIterator(ImageTypeDefinitionInterface::class)] iterable $definitions,
        private readonly ThumbnailSizeFormat $thumbnailSizeFormat,
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
            $this->assertSizesAreWellFormed($definition);
            $map[$type->value] = $definition;
        }

        $this->definitions = $map;
    }

    public function get(ImageType $type): ImageTypeDefinitionInterface
    {
        return $this->definitions[$type->value] ?? throw new RuntimeException(sprintf('No image type definition registered for "%s".', $type->name));
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

    public function getAdminPreviewSize(ImageType $type): string
    {
        foreach ($this->get($type)->thumbnailSizes() as [$width, $height]) {
            if ($width === 350) {
                return $this->thumbnailSizeFormat->format($width, $height);
            }
        }

        throw new RuntimeException(sprintf('No 350-width admin preview thumbnail registered for image type "%s".', $type->name));
    }

    /**
     * @return array<string, int>
     */
    public function getThumbnailSizeList(): array
    {
        $list = [];
        foreach ($this->definitions as $definition) {
            foreach ($definition->thumbnailSizes() as [$width, $height]) {
                $list[$this->thumbnailSizeFormat->format($width, $height)] = 0;
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

    private function assertSizesAreWellFormed(ImageTypeDefinitionInterface $definition): void
    {
        $free = ImageTypeDefinitionInterface::FREE_AXIS;
        foreach ($definition->thumbnailSizes() as [$width, $height]) {
            $bothAxesFree = $width === $free && $height === $free;
            $widthOutOfRange = $width !== $free && $width < 1;
            $heightOutOfRange = $height !== $free && $height < 1;
            if ($bothAxesFree || $widthOutOfRange || $heightOutOfRange) {
                throw new RuntimeException(sprintf(
                    'Invalid thumbnail size [%d, %d] in %s: at most one axis may be free (%d), the other must be a positive pixel count.',
                    $width,
                    $height,
                    $definition::class,
                    $free,
                ));
            }
        }
    }
}
