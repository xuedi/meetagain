<?php declare(strict_types=1);

namespace App\Cms\ReservedSlug;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes slugs that the CMS editor must never assign to a page.
 *
 * All implementations are collected and their results unioned: a slug returned
 * by any provider is reserved. Return the slugs this provider claims; return an
 * empty iterable when it claims none.
 */
#[AutoconfigureTag]
interface ReservedSlugProviderInterface
{
    /**
     * @return iterable<string>
     */
    public function getReservedSlugs(): iterable;
}
