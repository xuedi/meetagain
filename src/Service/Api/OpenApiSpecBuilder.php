<?php declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\OpenApiCollisionException;
use App\Plugin;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Assembles the public OpenAPI 3.1 spec served at /api/openapi.json from:
 *   - core's `config/api/openapi.json` (the base)
 *   - plus every plugin's `getOpenApiFragment()` contribution
 *
 * Collisions on paths, schemas, or tags surface as OpenApiCollisionException
 * rather than silently overwriting; that is a configuration bug we want loud.
 *
 * Result is cached in the app pool. Cache invalidates whenever cache.app is
 * cleared (every `just appAssets` does this; plugin enable/disable clears too).
 */
readonly class OpenApiSpecBuilder
{
    private const string CACHE_KEY = 'api.openapi_spec';

    /**
     * @param iterable<Plugin> $plugins
     */
    public function __construct(
        #[AutowireIterator(Plugin::class)]
        private iterable $plugins,
        private CacheInterface $appCache,
        private string $kernelProjectDir,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->appCache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(null);

            return $this->assemble();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(): array
    {
        $spec = $this->loadCoreSpec();
        $spec['paths'] ??= [];
        $spec['components'] ??= [];
        $spec['components']['schemas'] ??= [];
        $spec['tags'] ??= [];

        $pathOwners = [];
        foreach ($spec['paths'] as $path => $_) {
            $pathOwners[(string) $path] = 'core';
        }
        $schemaOwners = [];
        foreach ($spec['components']['schemas'] as $name => $_) {
            $schemaOwners[(string) $name] = 'core';
        }
        $tagOwners = [];
        foreach ($spec['tags'] as $tag) {
            $name = is_array($tag) && isset($tag['name']) ? (string) $tag['name'] : null;
            if ($name !== null) {
                $tagOwners[$name] = 'core';
            }
        }

        foreach ($this->plugins as $plugin) {
            $fragment = $plugin->getOpenApiFragment();
            if ($fragment === []) {
                continue;
            }
            $owner = $plugin->getPluginKey();
            $this->mergeFragment($spec, $fragment, $owner, $pathOwners, $schemaOwners, $tagOwners);
        }

        return $spec;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCoreSpec(): array
    {
        $path = $this->kernelProjectDir . '/config/api/openapi.json';
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $fragment
     * @param array<string, string> $pathOwners
     * @param array<string, string> $schemaOwners
     * @param array<string, string> $tagOwners
     */
    private function mergeFragment(
        array &$spec,
        array $fragment,
        string $owner,
        array &$pathOwners,
        array &$schemaOwners,
        array &$tagOwners,
    ): void {
        foreach ((array) ($fragment['paths'] ?? []) as $path => $definition) {
            $key = (string) $path;
            if (isset($pathOwners[$key])) {
                throw new OpenApiCollisionException(sprintf(
                    "OpenAPI path collision: %s declared by both '%s' and '%s'.",
                    $key,
                    $pathOwners[$key],
                    $owner,
                ));
            }
            $spec['paths'][$key] = $definition;
            $pathOwners[$key] = $owner;
        }

        foreach ((array) ($fragment['components']['schemas'] ?? []) as $name => $definition) {
            $key = (string) $name;
            if (isset($schemaOwners[$key])) {
                throw new OpenApiCollisionException(sprintf(
                    "OpenAPI schema collision: %s declared by both '%s' and '%s'.",
                    $key,
                    $schemaOwners[$key],
                    $owner,
                ));
            }
            $spec['components']['schemas'][$key] = $definition;
            $schemaOwners[$key] = $owner;
        }

        foreach ((array) ($fragment['tags'] ?? []) as $tag) {
            $name = is_array($tag) && isset($tag['name']) ? (string) $tag['name'] : null;
            if ($name === null) {
                continue;
            }
            if (isset($tagOwners[$name])) {
                throw new OpenApiCollisionException(sprintf(
                    "OpenAPI tag collision: '%s' declared by both '%s' and '%s'.",
                    $name,
                    $tagOwners[$name],
                    $owner,
                ));
            }
            $spec['tags'][] = $tag;
            $tagOwners[$name] = $owner;
        }
    }
}
