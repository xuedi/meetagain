<?php declare(strict_types=1);

namespace Plugin\Ranking\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Ranking\Repository\MemberRankRepository;

#[ORM\Entity(repositoryClass: MemberRankRepository::class)]
#[ORM\Table(name: 'ranking_member_rank')]
#[ORM\UniqueConstraint(name: 'unique_member_rank_user_group', columns: ['user_id', 'group_id'])]
class MemberRank
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(name: 'group_id')]
    private int $groupId;

    /**
     * For numeric archetypes (Elo, Points). null when a tiered archetype is in use.
     */
    #[ORM\Column(nullable: true)]
    private ?int $numericValue = null;

    /**
     * For tiered archetypes (KyuDan, Belt, Division). Plain int (no FK) so a
     * preset reload can drop and recreate definitions without cascade chaos.
     */
    #[ORM\Column(name: 'rank_definition_id', nullable: true)]
    private ?int $rankDefinitionId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new DateTimeImmutable();
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

    public function getNumericValue(): ?int
    {
        return $this->numericValue;
    }

    public function setNumericValue(?int $numericValue): static
    {
        $this->numericValue = $numericValue;

        return $this;
    }

    public function getRankDefinitionId(): ?int
    {
        return $this->rankDefinitionId;
    }

    public function setRankDefinitionId(?int $rankDefinitionId): static
    {
        $this->rankDefinitionId = $rankDefinitionId;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
