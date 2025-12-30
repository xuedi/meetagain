<?php declare(strict_types=1);

namespace App\Entity;

use App\Entity\BlockType\Title as TitleType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Cms
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
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
                $objects[] = CmsBlockTypes::buildObject($block->getType(), $block->getJson(), $block->getImage());
            }
        }

        return new ArrayCollection($objects);
    }

    public function getPageTitle(string $language): ?string
    {
        $title = null;
        foreach ($this->blocks as $block) {
            if ($block->getLanguage() === $language && $block->getType() === CmsBlockTypes::Title) {
                $title = TitleType::fromJson($block->getJson())->title;
            }
        }

        return $title;
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
        // set the owning side to null (unless already changed)
        if ($this->blocks->removeElement($block) && $block->getPage() === $this) {
            $block->setPage(null);
        }

        return $this;
    }
}
