<?php declare(strict_types=1);

namespace Plugin\Ranking\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Ranking\Repository\RankDefinitionRepository;

#[ORM\Entity(repositoryClass: RankDefinitionRepository::class)]
#[ORM\Table(name: 'ranking_rank_definition')]
#[ORM\Index(name: 'idx_rank_definition_config_position', columns: ['config_id', 'position'])]
class RankDefinition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RankingConfig::class)]
    #[ORM\JoinColumn(name: 'config_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private RankingConfig $config;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $labelKey = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $colorHex = null;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfig(): RankingConfig
    {
        return $this->config;
    }

    public function setConfig(RankingConfig $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabelKey(): ?string
    {
        return $this->labelKey;
    }

    public function setLabelKey(?string $labelKey): static
    {
        $this->labelKey = $labelKey;

        return $this;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function setColorHex(?string $colorHex): static
    {
        $this->colorHex = $colorHex;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
