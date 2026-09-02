<?php declare(strict_types=1);

namespace App\Publisher\Noindex;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface NoindexProviderInterface
{
    /**
     * Return true to keep the request's page out of search indexes, false to defer to the next provider.
     */
    public function shouldNoindex(Request $request): bool;
}
