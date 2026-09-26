<?php declare(strict_types=1);

namespace Plugin\Karaoke\Publisher\PluginSettings;

use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Service\Security\SecretBox;
use Plugin\Karaoke\Form\ConfigType;
use Plugin\Karaoke\ValueObject\Config;
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
        return 'karaoke';
    }

    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'karaoke_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return 'karaoke_config.intro';
    }

    public function getFormType(): string
    {
        return ConfigType::class;
    }

    public function getFormOptions(object $data): array
    {
        \assert($data instanceof Config);

        return ['youtube_api_key_set' => $data->getEncryptedYoutubeApiKey() !== null];
    }

    public function createDefault(): object
    {
        return new Config();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof Config);

        $key = $form->get('youtubeApiKey')->getData();
        $clear = (bool) $form->get('clearYoutubeApiKey')->getData();

        if ($clear) {
            $data->setEncryptedYoutubeApiKey(null);
        } elseif ($key !== null && $key !== '') {
            $data->setEncryptedYoutubeApiKey($this->secretBox->encrypt($key));
            $this->warnOnScopedKey();
        }
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function warnOnScopedKey(): void
    {
        $scopeId = $this->activeScopeId();
        if ($scopeId === null) {
            return;
        }

        $this->logger->warning('Karaoke: a YouTube Data API key was saved outside the global scope.', [
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
