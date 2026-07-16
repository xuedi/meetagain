<?php declare(strict_types=1);

namespace Plugin\Voting\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use Plugin\Voting\Config\VotingConfig;

/**
 * Single read path for the effective voting config in the current request. Delegates to the
 * resolver (per-scope override, else global, else neutral default) and memoizes.
 */
class VotingConfigService
{
    private ?VotingConfig $memo = null;

    public function __construct(
        private readonly PluginSettingsResolver $resolver,
    ) {}

    public function getConfig(): VotingConfig
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('voting');
        \assert($config instanceof VotingConfig);

        return $this->memo = $config;
    }
}
