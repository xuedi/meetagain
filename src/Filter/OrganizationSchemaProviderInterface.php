<?php declare(strict_types=1);

namespace App\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for organization schema providers.
 * Plugins can implement this to supply a custom schema.org/Organization (or LocalBusiness)
 * JSON-LD block — e.g. multisite plugin injects the group's identity on whitelabel domains.
 *
 * Return null to use the platform-level Organization schema from ConfigService.
 *
 * @return array<string, mixed>|null Schema.org object as PHP array, or null to defer
 */
#[AutoconfigureTag]
interface OrganizationSchemaProviderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function getOrganizationSchema(): ?array;
}
