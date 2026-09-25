<?php declare(strict_types=1);

namespace Plugin\Karaoke\Security;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Security\SecretBoxConsumerInterface;
use Override;
use Plugin\Karaoke\ValueObject\Config;

final readonly class ApiKeyConsumer implements SecretBoxConsumerInterface
{
    public function __construct(
        private Resolver $resolver,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'karaoke_config.secretbox_label';
    }

    #[Override]
    public function count(): int
    {
        $global = $this->resolver->resolveStore('karaoke', null)?->load('karaoke', null);

        return $global instanceof Config && $global->getEncryptedYoutubeApiKey() !== null ? 1 : 0;
    }
}
