<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @deprecated since 2026-04-30, use {@see \App\Admin\Navigation\AdminNavigationInterface} instead.
 *             This interface will be removed once all admin controllers have migrated to the new
 *             Admin\Navigation module. See plan 2026-04-30_admin-top-component.md.
 */
#[AutoconfigureTag]
interface AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig;
}
