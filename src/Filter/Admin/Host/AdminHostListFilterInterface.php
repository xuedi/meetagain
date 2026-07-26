<?php declare(strict_types=1);

namespace App\Filter\Admin\Host;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the hosts visible in the admin list. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface AdminHostListFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getHostIdFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isHostAccessible(int $hostId): ?bool;

    /**
     * @return array<string, mixed>
     */
    public function getDebugContext(int $hostId): array;
}
