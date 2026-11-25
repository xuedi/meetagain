<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\Publisher\PluginSettings\Resolver;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Contracts\Service\ResetInterface;

class ConfigService implements ResetInterface
{
    public const string KEY = 'fediverse';

    private ?Config $ceiling = null;

    /** @var array<string, Config> */
    private array $memo = [];

    public function __construct(
        private readonly Resolver $resolver,
    ) {}

    public function getConfig(): Config
    {
        return $this->getConfigFor($this->resolver->resolveScopeId());
    }

    public function getConfigFor(?string $scopeId): Config
    {
        return $this->memo[$scopeId ?? ''] ??= $this->effective($scopeId);
    }

    public function getCeiling(): Config
    {
        return $this->ceiling ??= $this->load(null) ?? new Config();
    }

    public function reset(): void
    {
        $this->ceiling = null;
        $this->memo = [];
    }

    private function effective(?string $scopeId): Config
    {
        $ceiling = $this->getCeiling();
        if ($scopeId === null) {
            return $ceiling;
        }

        $own = $this->load($scopeId);

        return $own === null ? $ceiling->withoutAccount() : $own->clampedTo($ceiling);
    }

    private function load(?string $scopeId): ?Config
    {
        $stored = $this->resolver->resolveStore(self::KEY, $scopeId)?->load(self::KEY, $scopeId);

        return $stored instanceof Config ? $stored : null;
    }
}
