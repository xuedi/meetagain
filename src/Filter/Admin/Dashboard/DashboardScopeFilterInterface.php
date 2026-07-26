<?php declare(strict_types=1);

namespace App\Filter\Admin\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the events and users the admin dashboard aggregates over. Implementations compose
 * with AND-intersection; each getter returns null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface DashboardScopeFilterInterface
{
    public function getPriority(): int;

    /**
     * @return array<int>|null
     */
    public function getEventIdFilter(): ?array;

    /**
     * @return array<int>|null
     */
    public function getUserIdFilter(): ?array;
}
