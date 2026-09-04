<?php declare(strict_types=1);

namespace Plugin\Boardgames\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Boardgames\Enum\CopyCondition;
use Plugin\Boardgames\Repository\GameOwnershipRepository;

#[ORM\Entity(repositoryClass: GameOwnershipRepository::class)]
#[ORM\Table(name: 'plg_boardgames_ownership')]
#[ORM\UniqueConstraint(name: 'uniq_boardgames_ownership_user_game', columns: ['user_id', 'game_id'])]
class GameOwnership
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Game $game = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $copyLanguage = null;

    #[ORM\Column(length: 16, nullable: true, enumType: CopyCondition::class)]
    private ?CopyCondition $copyCondition = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $canTeach = false;

    #[ORM\Column]
    private bool $willingToBring = true;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $acquiredAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getCopyLanguage(): ?string
    {
        return $this->copyLanguage;
    }

    public function setCopyLanguage(?string $copyLanguage): static
    {
        $this->copyLanguage = $copyLanguage;

        return $this;
    }

    public function getCopyCondition(): ?CopyCondition
    {
        return $this->copyCondition;
    }

    public function setCopyCondition(?CopyCondition $copyCondition): static
    {
        $this->copyCondition = $copyCondition;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function isCanTeach(): bool
    {
        return $this->canTeach;
    }

    public function setCanTeach(bool $canTeach): static
    {
        $this->canTeach = $canTeach;

        return $this;
    }

    public function isWillingToBring(): bool
    {
        return $this->willingToBring;
    }

    public function setWillingToBring(bool $willingToBring): static
    {
        $this->willingToBring = $willingToBring;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getAcquiredAt(): ?DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    public function setAcquiredAt(?DateTimeImmutable $acquiredAt): static
    {
        $this->acquiredAt = $acquiredAt;

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

    public function isAskable(): bool
    {
        return $this->isPublic && $this->willingToBring;
    }
}
