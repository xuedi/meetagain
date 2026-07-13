<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

/**
 * (De)serialization contract for a settings data object stored by the generic store.
 * Adopters whose DTO is persisted as JSON implement this; plugins shipping a custom
 * store (with their own entity) do not need it.
 */
interface PluginSettingsData
{
    /** @return array<string, mixed> */
    public function toArray(): array;

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): static;
}
