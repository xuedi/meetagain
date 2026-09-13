<?php declare(strict_types=1);

namespace App\Portability;

use App\Publisher\PluginSettings\Data;
use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\SecretKeysInterface;
use App\Service\Admin\PluginSettingsService;

readonly class PluginSettings
{
    public function __construct(
        private PluginSettingsService $descriptors,
        private Resolver $resolver,
    ) {}

    /**
     * @param list<string> $pluginKeys
     * @return array<string, array<string, mixed>>
     */
    public function export(array $pluginKeys): array
    {
        $block = [];
        foreach ($this->descriptors->getProviders() as $key => $descriptor) {
            if (!in_array($descriptor->getPluginKey(), $pluginKeys, true)) {
                continue;
            }

            $data = $this->resolver->resolve($key);
            if ($data instanceof Data) {
                $block[$key] = $this->withoutSecrets($data, $data->toArray());
            }
        }
        ksort($block);

        return $block;
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function apply(array $block): void
    {
        foreach ($block as $key => $values) {
            $key = (string) $key;
            $descriptor = $this->descriptors->getProvider($key);
            if ($descriptor === null || !is_array($values)) {
                continue;
            }

            $store = $this->resolver->resolveStore($key, null);
            $current = $store?->load($key, null) ?? $descriptor->createDefault();
            if ($store === null || !$current instanceof Data) {
                continue;
            }

            $merged = array_replace($current->toArray(), $this->withoutSecrets($current, $values));
            $store->save($key, $current::fromArray($merged), null);
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutSecrets(Data $data, array $values): array
    {
        $secretKeys = $data instanceof SecretKeysInterface ? $data->getSecretKeys() : [];

        $kept = [];
        foreach ($values as $name => $value) {
            if (!is_string($name) || in_array($name, $secretKeys, true)) {
                continue;
            }

            $kept[$name] = $value;
        }

        return $kept;
    }
}
