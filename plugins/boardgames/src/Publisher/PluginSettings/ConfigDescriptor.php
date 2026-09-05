<?php declare(strict_types=1);

namespace Plugin\Boardgames\Publisher\PluginSettings;

use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Service\Security\SecretBox;
use Plugin\Boardgames\Form\ConfigType;
use Plugin\Boardgames\ValueObject\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\FormInterface;

final readonly class ConfigDescriptor implements DescriptorInterface
{
    /**
     * @param iterable<ScopeProviderInterface> $scopeProviders
     */
    public function __construct(
        private SecretBox $secretBox,
        private LoggerInterface $logger,
        #[AutowireIterator(ScopeProviderInterface::class)]
        private iterable $scopeProviders = [],
    ) {}

    public function getKey(): string
    {
        return 'boardgames';
    }

    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'boardgames_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return 'boardgames_config.intro';
    }

    public function getFormType(): string
    {
        return ConfigType::class;
    }

    public function getFormOptions(object $data): array
    {
        \assert($data instanceof Config);

        return ['bgg_token_set' => $data->getEncryptedBggToken() !== null];
    }

    public function createDefault(): object
    {
        return new Config();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof Config);

        $token = $form->get('bggToken')->getData();
        $clear = (bool) $form->get('clearBggToken')->getData();

        if ($clear) {
            $data->setEncryptedBggToken(null);
        } elseif ($token !== null && $token !== '') {
            $data->setEncryptedBggToken($this->secretBox->encrypt($token));
            $this->warnOnScopedToken();
        }

        if (!$data->isCirculation()) {
            $data->setTrustSystem(false);
        }
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function warnOnScopedToken(): void
    {
        $scopeId = $this->activeScopeId();
        if ($scopeId === null) {
            return;
        }

        $this->logger->warning('Boardgames: a BoardGameGeek token was saved outside the global scope; the standing licence condition applies.', [
            'scopeId' => $scopeId,
        ]);
    }

    private function activeScopeId(): ?string
    {
        foreach ($this->scopeProviders as $provider) {
            $scopeId = $provider->getScopeId();
            if ($scopeId !== null) {
                return $scopeId;
            }
        }

        return null;
    }
}
