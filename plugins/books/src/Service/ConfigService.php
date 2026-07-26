<?php declare(strict_types=1);

namespace Plugin\Books\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use Plugin\Books\ValueObject\Config;

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

        $config = $this->resolver->resolve('books');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }
}
