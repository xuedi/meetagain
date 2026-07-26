<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Persists a plugin's settings data object for one scope. The highest-priority store whose
 * supports() is true owns load/save for that (key, scopeId), so a custom store outranks the
 * generic fallback. scopeId === null addresses the global record; any other id is opaque and
 * comes from a scope provider.
 */
#[AutoconfigureTag]
interface PluginSettingsStoreInterface
{
    public function supports(string $key, ?string $scopeId): bool;

    /** Return null when nothing is stored for that scope. */
    public function load(string $key, ?string $scopeId): ?object;

    public function save(string $key, object $data, ?string $scopeId): void;

    /** Higher wins when several stores support the same (key, scopeId). */
    public function getPriority(): int;
}
