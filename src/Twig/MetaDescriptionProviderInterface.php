<?php declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for meta description providers.
 * Plugins can implement this to supply context-aware meta descriptions.
 *
 * Return null to indicate no value for the given context,
 * allowing the next provider or the built-in fallback to be used.
 */
#[AutoconfigureTag]
interface MetaDescriptionProviderInterface
{
    public function getMetaDescription(string $context): ?string;
}
