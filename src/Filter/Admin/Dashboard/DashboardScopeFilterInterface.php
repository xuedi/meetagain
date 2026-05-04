<?php declare(strict_types=1);

namespace App\Filter\Admin\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Restricts what subset of events and users the admin dashboard renders data for.
 *
 * Implementations return either:
 *   null  - no opinion: this filter does not constrain anything
 *   []    - block: aggregate over zero rows (caller renders an empty state)
 *   array - intersect with other filters' results
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
