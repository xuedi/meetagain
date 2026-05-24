<?php declare(strict_types=1);

namespace Plugin\Ranking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Ranking\Enum\Archetype;
use Plugin\Ranking\Repository\RankingConfigRepository;

#[ORM\Entity(repositoryClass: RankingConfigRepository::class)]
#[ORM\Table(name: 'ranking_config')]
#[ORM\UniqueConstraint(name: 'unique_ranking_config_group', columns: ['group_id'])]
class RankingConfig
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'group_id')]
    private int $groupId;

    #[ORM\Column(length: 20, enumType: Archetype::class)]
    private Archetype $archetype = Archetype::Elo;

    #[ORM\Column]
    private bool $showBadge = true;

    #[ORM\Column]
    private bool $showOnMemberList = true;

    #[ORM\Column]
    private bool $showLeaderboardNav = true;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getArchetype(): Archetype
    {
        return $this->archetype;
    }

    public function setArchetype(Archetype $archetype): static
    {
        $this->archetype = $archetype;

        return $this;
    }

    public function isShowBadge(): bool
    {
        return $this->showBadge;
    }

    public function setShowBadge(bool $showBadge): static
    {
        $this->showBadge = $showBadge;

        return $this;
    }

    public function isShowOnMemberList(): bool
    {
        return $this->showOnMemberList;
    }

    public function setShowOnMemberList(bool $showOnMemberList): static
    {
        $this->showOnMemberList = $showOnMemberList;

        return $this;
    }

    public function isShowLeaderboardNav(): bool
    {
        return $this->showLeaderboardNav;
    }

    public function setShowLeaderboardNav(bool $showLeaderboardNav): static
    {
        $this->showLeaderboardNav = $showLeaderboardNav;

        return $this;
    }
}
