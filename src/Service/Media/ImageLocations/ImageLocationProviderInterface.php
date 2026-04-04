<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ImageLocationProviderInterface
{
    /** Returns the ImageType this provider handles — also used as location discriminator. */
    public function getType(): ImageType;

    /**
     * Returns all (imageId, locationId) pairs currently in use at this location.
     * Used internally by sync() and for inspection/testing.
     *
     * @return array<array{imageId: int, locationId: int}>
     */
    public function discoverImageIds(): array;

    /** Reads current DB state, diffs against discovered IDs, and applies partial delete/insert. */
    public function sync(): void;

    /**
     * Returns an admin route + params for editing the entity that owns this location,
     * or null if no edit route exists.
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function getEditLink(int $locationId): ?array;
}
