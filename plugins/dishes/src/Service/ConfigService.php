<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use App\Publisher\PluginSettings\Resolver;
use Plugin\Dishes\ValueObject\Config;

class ConfigService
{
    private ?Config $memo = null;

    public function __construct(
        private readonly Resolver $resolver,
    ) {}

    public function getConfig(): Config
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('dishes');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }
}
