<?php declare(strict_types=1);

namespace App\Publisher\OrganizationSchema;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface OrganizationSchemaProviderInterface
{
    /**
     * Return the schema.org/Organization (or LocalBusiness) JSON-LD block as a PHP array,
     * or null to defer to the next provider (or the default from ConfigService).
     *
     * @return array<string, mixed>|null
     */
    public function getOrganizationSchema(): ?array;
}
