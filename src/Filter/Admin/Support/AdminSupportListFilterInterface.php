<?php declare(strict_types=1);

namespace App\Filter\Admin\Support;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the support requests visible in the admin list. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface AdminSupportListFilterInterface
{
    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getRequestIdFilter(): ?array;
}
