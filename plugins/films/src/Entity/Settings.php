<?php declare(strict_types=1);

namespace Plugin\Films\Entity;

use App\Publisher\PluginSettings\Data;
use App\Publisher\PluginSettings\SecretKeysInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Plugin\Films\Repository\SettingsRepository;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'plg_films_settings')]
class Settings implements Data, SecretKeysInterface
{
    private const string KEY_TMDB = 'encryptedTmdbKey';
    private const string KEY_OMDB = 'encryptedOmdbKey';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: ExternalSource::class, nullable: true)]
    private ?ExternalSource $adapter = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedTmdbKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedOmdbKey = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdapter(): ?ExternalSource
    {
        return $this->adapter;
    }

    public function setAdapter(?ExternalSource $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function getEncryptedTmdbKey(): ?string
    {
        return $this->encryptedTmdbKey;
    }

    public function setEncryptedTmdbKey(?string $encryptedTmdbKey): static
    {
        $this->encryptedTmdbKey = $encryptedTmdbKey;

        return $this;
    }

    public function getEncryptedOmdbKey(): ?string
    {
        return $this->encryptedOmdbKey;
    }

    public function setEncryptedOmdbKey(?string $encryptedOmdbKey): static
    {
        $this->encryptedOmdbKey = $encryptedOmdbKey;

        return $this;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'adapter' => $this->adapter?->value,
            self::KEY_TMDB => $this->encryptedTmdbKey,
            self::KEY_OMDB => $this->encryptedOmdbKey,
        ];
    }

    #[Override]
    public static function fromArray(array $raw): static
    {
        $adapter = $raw['adapter'] ?? null;
        $tmdbKey = $raw[self::KEY_TMDB] ?? null;
        $omdbKey = $raw[self::KEY_OMDB] ?? null;

        // @mago-expect analyzer:unsafe-instantiation
        $settings = new static();
        $settings->setAdapter(is_string($adapter) ? ExternalSource::tryFrom($adapter) : null);
        $settings->setEncryptedTmdbKey(is_string($tmdbKey) ? $tmdbKey : null);
        $settings->setEncryptedOmdbKey(is_string($omdbKey) ? $omdbKey : null);

        return $settings;
    }

    #[Override]
    public function getSecretKeys(): array
    {
        return [self::KEY_TMDB, self::KEY_OMDB];
    }
}
