<?php declare(strict_types=1);

namespace Plugin\Ranking\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Ranking\Enum\RankChangeReason;
use Plugin\Ranking\Repository\RankHistoryRepository;

#[ORM\Entity(repositoryClass: RankHistoryRepository::class)]
#[ORM\Table(name: 'ranking_rank_history')]
#[ORM\Index(name: 'idx_rank_history_user_group_created', columns: ['user_id', 'group_id', 'created_at'])]
class RankHistory
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(name: 'group_id')]
    private int $groupId;

    #[ORM\Column(name: 'actor_user_id')]
    private int $actorUserId;

    #[ORM\Column(length: 30, enumType: RankChangeReason::class)]
    private RankChangeReason $reason;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function setGroupId(int $groupId): static
    {
        $this->groupId = $groupId;

        return $this;
    }

    public function getActorUserId(): int
    {
        return $this->actorUserId;
    }

    public function setActorUserId(int $actorUserId): static
    {
        $this->actorUserId = $actorUserId;

        return $this;
    }

    public function getReason(): RankChangeReason
    {
        return $this->reason;
    }

    public function setReason(RankChangeReason $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): static
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): static
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
