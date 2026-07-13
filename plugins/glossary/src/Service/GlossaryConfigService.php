<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use Plugin\Glossary\Config\GlossaryConfig;

/**
 * Single read path for the effective glossary config in the current request. Delegates to
 * the resolver (per-scope override, else global, else neutral default) and memoizes.
 */
class GlossaryConfigService
{
    private ?GlossaryConfig $memo = null;

    public function __construct(
        private readonly PluginSettingsResolver $resolver,
    ) {}

    public function getConfig(): GlossaryConfig
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('glossary');
        \assert($config instanceof GlossaryConfig);

        return $this->memo = $config;
    }
}
