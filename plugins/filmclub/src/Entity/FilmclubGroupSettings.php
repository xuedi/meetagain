<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;

#[ORM\Entity(repositoryClass: FilmclubGroupSettingsRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_filmclub_group', columns: ['group_id'])]
class FilmclubGroupSettings
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $groupId = null;

    #[ORM\Column(enumType: ExternalSource::class, nullable: true)]
    private ?ExternalSource $adapter = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedTmdbKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedOmdbKey = null;

    #[ORM\Column]
    private int $defaultPollDurationDays = 7;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function setGroupId(int $groupId): static
    {
        $this->groupId = $groupId;

        return $this;
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

    public function getDefaultPollDurationDays(): int
    {
        return $this->defaultPollDurationDays;
    }

    public function setDefaultPollDurationDays(int $defaultPollDurationDays): static
    {
        $this->defaultPollDurationDays = $defaultPollDurationDays;

        return $this;
    }
}
