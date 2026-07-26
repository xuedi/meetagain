<?php declare(strict_types=1);

namespace App\Filter\Admin\Location;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the locations visible in the admin list. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface AdminLocationListFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getLocationIdFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isLocationAccessible(int $locationId): ?bool;

    /**
     * @return array<string, mixed>
     */
    public function getDebugContext(int $locationId): array;
}
