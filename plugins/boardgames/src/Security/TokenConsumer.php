<?php declare(strict_types=1);

namespace Plugin\Boardgames\Security;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Security\SecretBoxConsumerInterface;
use Override;
use Plugin\Boardgames\ValueObject\Config;

final readonly class TokenConsumer implements SecretBoxConsumerInterface
{
    public function __construct(
        private Resolver $resolver,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'boardgames_config.secretbox_label';
    }

    #[Override]
    public function count(): int
    {
        $global = $this->resolver->resolveStore('boardgames', null)?->load('boardgames', null);

        return $global instanceof Config && $global->getEncryptedBggToken() !== null ? 1 : 0;
    }
}
