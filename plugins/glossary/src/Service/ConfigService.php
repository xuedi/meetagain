<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use Plugin\Glossary\ValueObject\Config;

/**
 * Single read path for the effective glossary config in the current request. Delegates to
 * the resolver (per-scope override, else global, else neutral default) and memoizes.
 */
class ConfigService
{
    private ?Config $memo = null;

    public function __construct(
        private readonly PluginSettingsResolver $resolver,
    ) {}

    public function getConfig(): Config
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('glossary');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }
}
