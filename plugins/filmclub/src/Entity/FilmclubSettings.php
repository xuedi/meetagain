<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;

#[ORM\Entity(repositoryClass: FilmclubSettingsRepository::class)]
class FilmclubSettings
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ExternalSource::class, nullable: true)]
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
}
