<?php declare(strict_types=1);

namespace App\Publisher\MetaDescription;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the meta description for a context; null defers to the next provider, then the fallback.
 */
#[AutoconfigureTag]
interface MetaDescriptionProviderInterface
{
    public function getMetaDescription(string $context): ?string;
}
