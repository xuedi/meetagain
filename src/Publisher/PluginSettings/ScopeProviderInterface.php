<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the id of the active override scope for the current request. The resolver takes the
 * first non-null id from the chain; null means "no override, use the global record". The id is
 * opaque to core - only the store that persists that scope interprets it.
 */
#[AutoconfigureTag]
interface ScopeProviderInterface
{
    public function getScopeId(): ?string;

    /**
     * Runs the work with the given scope active, so everything resolved inside it sees that scope as a
     * request served in it would. A provider that does not own the id runs the work unchanged.
     *
     * @template T
     * @param  callable():T $work
     * @return T
     */
    public function runIn(string $scopeId, callable $work): mixed;
}
