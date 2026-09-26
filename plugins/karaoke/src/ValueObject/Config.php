<?php declare(strict_types=1);

namespace Plugin\Karaoke\ValueObject;

use App\Publisher\PluginSettings\Data;
use App\Publisher\PluginSettings\SecretKeysInterface;
use SensitiveParameter;

final class Config implements Data, SecretKeysInterface
{
    private bool $lookupEnabled = true;

    private bool $lrclibEnabled = true;

    private ?string $encryptedYoutubeApiKey = null;

    public function isLookupEnabled(): bool
    {
        return $this->lookupEnabled;
    }

    public function setLookupEnabled(bool $lookupEnabled): static
    {
        $this->lookupEnabled = $lookupEnabled;

        return $this;
    }

    public function isLrclibEnabled(): bool
    {
        return $this->lrclibEnabled;
    }

    public function setLrclibEnabled(bool $lrclibEnabled): static
    {
        $this->lrclibEnabled = $lrclibEnabled;

        return $this;
    }

    public function getEncryptedYoutubeApiKey(): ?string
    {
        return $this->encryptedYoutubeApiKey;
    }

    public function setEncryptedYoutubeApiKey(#[SensitiveParameter] ?string $encryptedYoutubeApiKey): static
    {
        $this->encryptedYoutubeApiKey = $encryptedYoutubeApiKey === '' ? null : $encryptedYoutubeApiKey;

        return $this;
    }

    public function getSecretKeys(): array
    {
        return ['encryptedYoutubeApiKey'];
    }

    public function toArray(): array
    {
        return [
            'lookupEnabled' => $this->lookupEnabled,
            'lrclibEnabled' => $this->lrclibEnabled,
            'encryptedYoutubeApiKey' => $this->encryptedYoutubeApiKey,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->setLookupEnabled((bool) ($raw['lookupEnabled'] ?? true));
        $config->setLrclibEnabled((bool) ($raw['lrclibEnabled'] ?? true));
        $config->setEncryptedYoutubeApiKey(isset($raw['encryptedYoutubeApiKey']) ? (string) $raw['encryptedYoutubeApiKey'] : null);

        return $config;
    }
}
