<?php declare(strict_types=1);

namespace Plugin\Boardgames\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Repository\GameRepository;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'plg_boardgames_game')]
#[ORM\UniqueConstraint(name: 'uniq_boardgames_game_external', columns: ['external_source', 'external_id'])]
class Game
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearPublished = null;

    #[ORM\Column(nullable: true)]
    private ?int $minPlayers = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxPlayers = null;

    #[ORM\Column(nullable: true)]
    private ?int $bestPlayerCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $minPlaytime = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxPlaytime = null;

    #[ORM\Column(nullable: true)]
    private ?int $minAge = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 10, enumType: ExternalSource::class)]
    private ExternalSource $externalSource = ExternalSource::Manual;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $externalId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Image $boxImage = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getYearPublished(): ?int
    {
        return $this->yearPublished;
    }

    public function setYearPublished(?int $yearPublished): static
    {
        $this->yearPublished = $yearPublished;

        return $this;
    }

    public function getMinPlayers(): ?int
    {
        return $this->minPlayers;
    }

    public function setMinPlayers(?int $minPlayers): static
    {
        $this->minPlayers = $minPlayers;

        return $this;
    }

    public function getMaxPlayers(): ?int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(?int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    public function getBestPlayerCount(): ?int
    {
        return $this->bestPlayerCount;
    }

    public function setBestPlayerCount(?int $bestPlayerCount): static
    {
        $this->bestPlayerCount = $bestPlayerCount;

        return $this;
    }

    public function getMinPlaytime(): ?int
    {
        return $this->minPlaytime;
    }

    public function setMinPlaytime(?int $minPlaytime): static
    {
        $this->minPlaytime = $minPlaytime;

        return $this;
    }

    public function getMaxPlaytime(): ?int
    {
        return $this->maxPlaytime;
    }

    public function setMaxPlaytime(?int $maxPlaytime): static
    {
        $this->maxPlaytime = $maxPlaytime;

        return $this;
    }

    public function getMinAge(): ?int
    {
        return $this->minAge;
    }

    public function setMinAge(?int $minAge): static
    {
        $this->minAge = $minAge;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getExternalSource(): ExternalSource
    {
        return $this->externalSource;
    }

    public function setExternalSource(ExternalSource $externalSource): static
    {
        $this->externalSource = $externalSource;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getBoxImage(): ?Image
    {
        return $this->boxImage;
    }

    public function setBoxImage(?Image $boxImage): static
    {
        $this->boxImage = $boxImage;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPlayerRange(): ?string
    {
        if ($this->minPlayers === null && $this->maxPlayers === null) {
            return null;
        }

        if ($this->minPlayers !== null && $this->maxPlayers !== null && $this->minPlayers !== $this->maxPlayers) {
            return $this->minPlayers . '-' . $this->maxPlayers;
        }

        return (string) ($this->minPlayers ?? $this->maxPlayers);
    }

    public function getPlaytimeRange(): ?string
    {
        if ($this->minPlaytime === null && $this->maxPlaytime === null) {
            return null;
        }

        if ($this->minPlaytime !== null && $this->maxPlaytime !== null && $this->minPlaytime !== $this->maxPlaytime) {
            return $this->minPlaytime . '-' . $this->maxPlaytime;
        }

        return (string) ($this->minPlaytime ?? $this->maxPlaytime);
    }
}
