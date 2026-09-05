<?php declare(strict_types=1);

namespace Plugin\Photos\Service;

use App\Publisher\PluginSettings\Resolver;
use Plugin\Photos\ValueObject\Config;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConfigService
{
    private ?Config $memo = null;

    public function __construct(
        private readonly Resolver $resolver,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function getConfig(): Config
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $config = $this->resolver->resolve('photos');
        \assert($config instanceof Config);

        return $this->memo = $config;
    }

    public function canUpload(): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_STEWARD')) {
            return true;
        }

        return $this->authorizationChecker->isGranted('ROLE_USER') && $this->getConfig()->isMemberUploads();
    }
}
