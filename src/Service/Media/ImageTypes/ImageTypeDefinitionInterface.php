<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Describes everything the application needs to know about one image type: its identity, the
 * thumbnail sizes and fit mode it renders, how its usages are discovered and synced, and how a
 * single usage is linked and labelled in the admin.
 *
 * Exactly one definition handles each ImageType; the type doubles as the location discriminator.
 */
#[AutoconfigureTag]
interface ImageTypeDefinitionInterface
{
    /** The ImageType this definition handles - identity and DB key. */
    public function getType(): ImageType;

    /**
     * The effective [width, height] thumbnail pairs for this type, including the universal
     * report and micro sizes supplied by the abstract base.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function thumbnailSizes(): array;

    /** How source images are fitted into their thumbnails. */
    public function fitMode(): ImageFitMode;

    /**
     * All (imageId, locationId) pairs currently in use for this type.
     *
     * @return array<array{imageId: int, locationId: int}>
     */
    public function discoverImageIds(): array;

    /** Diffs discovered usages against stored state and applies partial delete/insert. */
    public function sync(): void;

    /**
     * Admin route + params for editing the entity that owns a usage, or null if none exists.
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function getEditLink(int $locationId): ?array;

    /**
     * A human-readable label plus an optional admin route and params describing where an image is
     * used, or null when the usage cannot be resolved.
     *
     * @return array{label: string, route: string|null, params: array<string, mixed>}|null
     */
    public function locate(Image $image): ?array;
}
