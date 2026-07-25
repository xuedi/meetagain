<?php declare(strict_types=1);

namespace App\Localization;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reports and deletes per-locale content belonging to a caller-supplied set of owner ids.
 *
 * Implementations promise:
 *   - the caller decides which owner ids are in scope; the source never asks who owns them
 *   - an empty owner-id list or an empty keep-locale list yields nothing and deletes nothing
 *   - count, find and delete describe the same set for the same arguments
 */
#[AutoconfigureTag]
interface LocalizedContentSourceInterface
{
    public const string OWNER_EVENT = 'event';
    public const string OWNER_CMS = 'cms';
    public const string OWNER_IMAGE = 'image';

    public function getKey(): string;

    public function getLabelKey(): string;

    /** Names the owner-id set the caller has to supply for this source. */
    public function getOwnerType(): string;

    /**
     * @param list<int> $ownerIds
     * @param list<string> $keepLocales
     */
    public function countOutsideLocales(array $ownerIds, array $keepLocales): int;

    /**
     * @param list<int> $ownerIds
     * @param list<string> $keepLocales
     * @return list<LocalizedContentRow>
     */
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array;

    /**
     * @param list<int> $ownerIds
     * @param list<string> $keepLocales
     * @return int deleted entries
     */
    public function deleteOutsideLocales(array $ownerIds, array $keepLocales): int;
}
