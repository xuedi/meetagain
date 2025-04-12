<?php

namespace App\Entity;

use App\Entity\BlockType\Headline as HeadlineBlockType;
use App\Entity\BlockType\Image as ImageBlockType;
use App\Entity\BlockType\Text as TextBlockType;
use App\Entity\BlockType\Paragraph as ParagraphBlockType;
use App\Entity\BlockType\Hero as HeroBlockType;
use App\Entity\BlockType\EventTeaser as EventTeaserType;
use App\Repository\CmsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;

#[ORM\Entity(repositoryClass: CmsRepository::class)]
class Cms
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?bool $published = null;

    /**
     * @var Collection<int, CmsBlock>
     */
    #[ORM\OneToMany(targetEntity: CmsBlock::class, mappedBy: 'page', orphanRemoval: true)]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /**
     * @return Collection<int, CmsBlock>
     */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function getLanguageFilteredBlockJsonList(string $language): Collection
    {
        $objects = [];
        foreach ($this->blocks as $block) {
            if ($block->getLanguage() === $language) {
                $objects[] = match($block->getType()) {
                    CmsBlockTypes::Paragraph => ParagraphBlockType::fromJson($block->getJson()),
                    CmsBlockTypes::Headline => HeadlineBlockType::fromJson($block->getJson()),
                    CmsBlockTypes::Image => ImageBlockType::fromJson($block->getJson()),
                    CmsBlockTypes::Text => TextBlockType::fromJson($block->getJson()),
                    CmsBlockTypes::Hero => HeroBlockType::fromJson($block->getJson()),
                    CmsBlockTypes::EventTeaser => EventTeaserType::fromJson($block->getJson()),
                    default => throw new Exception('To be implemented'),
                };
            }
        }

        return new ArrayCollection($objects);
    }

    public function addBlock(CmsBlock $block): static
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
            $block->setPage($this);
        }

        return $this;
    }

    public function removeBlock(CmsBlock $block): static
    {
        if ($this->blocks->removeElement($block)) {
            // set the owning side to null (unless already changed)
            if ($block->getPage() === $this) {
                $block->setPage(null);
            }
        }

        return $this;
    }
}
