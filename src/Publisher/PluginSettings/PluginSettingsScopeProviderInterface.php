<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the id of the active override scope for the current request. The resolver
 * takes the first non-null id from the provider chain; null means "no override, use the
 * global record". The id is opaque to core - only the store that persists that scope
 * interprets it.
 *
 * Core registers no implementation, so standalone core always resolves to the global
 * scope. Implementations are auto-discovered via #[AutoconfigureTag].
 */
#[AutoconfigureTag]
interface PluginSettingsScopeProviderInterface
{
    public function getScopeId(): ?string;
}
