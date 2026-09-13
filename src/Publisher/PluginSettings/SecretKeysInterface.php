<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

/**
 * Names the toArray() keys of a settings data object that hold secrets bound to this
 * instance. Those values never leave it.
 */
interface SecretKeysInterface
{
    /** @return list<string> */
    public function getSecretKeys(): array;
}
