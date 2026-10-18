<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Security\SecretBox;
use Plugin\Karaoke\ValueObject\Config;
use Symfony\Contracts\Service\ResetInterface;

class ConfigService implements ResetInterface
{
    private ?Config $memo = null;

    public function __construct(
        private readonly Resolver $resolver,
        private readonly SecretBox $secretBox,
    ) {}

    public function getConfig(): Config
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('karaoke');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }

    public function getYoutubeApiKey(): ?string
    {
        $encrypted = $this->getConfig()->getEncryptedYoutubeApiKey();

        return $encrypted === null ? null : $this->secretBox->decrypt($encrypted);
    }

    public function reset(): void
    {
        $this->memo = null;
    }
}
