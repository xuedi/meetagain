<?php declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for plugins to enrich the email context before rendering.
 * Plugins implement this to inject additional variables (e.g. group-specific greeting)
 * into the email context at queue time, while the HTTP request is still available.
 */
#[AutoconfigureTag]
interface EmailContextEnricherInterface
{
    /**
     * Enrich the email context before template rendering.
     *
     * @param array<string, mixed> $context Existing context variables
     * @param string $locale The recipient's locale (e.g. 'en', 'de', 'cn')
     * @return array<string, mixed> The enriched context
     */
    public function enrich(array $context, string $locale): array;
}
