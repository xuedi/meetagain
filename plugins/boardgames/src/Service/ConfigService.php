<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Publisher\PluginSettings\Resolver;
use Plugin\Boardgames\ValueObject\Config;
use Symfony\Contracts\Service\ResetInterface;

class ConfigService implements ResetInterface
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

        $config = $this->resolver->resolve('boardgames');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }

    public function reset(): void
    {
        $this->memo = null;
    }
}
