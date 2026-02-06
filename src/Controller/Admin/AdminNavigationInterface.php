<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin controllers to define their navigation metadata.
 *
 * Controllers implement this interface to declare how they should appear
 * in the admin sidebar navigation. The AdminNavigationService automatically
 * discovers all implementations using autowiring.
 */
#[AutoconfigureTag]
interface AdminNavigationInterface
{
    /**
     * Define this controller's admin navigation metadata.
     *
     * Return null if this controller should not appear in navigation.
     * Instance method allows service injection for dynamic navigation.
     */
    public function getAdminNavigation(): ?AdminNavigationConfig;
}
