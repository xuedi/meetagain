<?php declare(strict_types=1);

namespace App\Cms\ReservedSlug;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes slugs the CMS editor must never assign to a page. Implementations are unioned.
 */
#[AutoconfigureTag]
interface ReservedSlugProviderInterface
{
    /**
     * @return iterable<string>
     */
    public function getReservedSlugs(): iterable;
}
