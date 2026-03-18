<?php declare(strict_types=1);

namespace App\Entity;

use App\Entity\BlockType\Text as TextType;
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

    #[ORM\Column]
    private ?bool $locked = null;

    /**
     * @var Collection<int, CmsMenuLocation>
     */
    #[ORM\OneToMany(targetEntity: CmsMenuLocation::class, mappedBy: 'cms', cascade: ['persist'], orphanRemoval: true)]
    private Collection $menuLocations;

    /**
     * @var Collection<int, CmsTitle>
     */
    #[ORM\OneToMany(targetEntity: CmsTitle::class, mappedBy: 'cms', cascade: ['persist'], orphanRemoval: true)]
    private Collection $titles;

    /**
     * @var Collection<int, CmsLinkName>
     */
    #[ORM\OneToMany(targetEntity: CmsLinkName::class, mappedBy: 'cms', cascade: ['persist'], orphanRemoval: true)]
    private Collection $linkNames;

    /**
     * @var Collection<int, CmsBlock>
     */
    #[ORM\OneToMany(targetEntity: CmsBlock::class, mappedBy: 'page', orphanRemoval: true)]
    private Collection $blocks;

    public function __construct()
    {
        $this->menuLocations = new ArrayCollection();
        $this->titles = new ArrayCollection();
        $this->linkNames = new ArrayCollection();
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

    public function isLocked(): ?bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): static
    {
        $this->locked = $locked;

        return $this;
    }

    /**
     * @return Collection<int, CmsMenuLocation>
     */
    public function getMenuLocations(): Collection
    {
        return $this->menuLocations;
    }

    public function addMenuLocation(CmsMenuLocation $menuLocation): static
    {
        if (!$this->menuLocations->contains($menuLocation)) {
            $this->menuLocations->add($menuLocation);
            $menuLocation->setCms($this);
        }

        return $this;
    }

    public function removeMenuLocation(CmsMenuLocation $menuLocation): static
    {
        if ($this->menuLocations->removeElement($menuLocation) && $menuLocation->getCms() === $this) {
            $menuLocation->setCms(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, CmsTitle>
     */
    public function getTitles(): Collection
    {
        return $this->titles;
    }

    public function addTitle(CmsTitle $title): static
    {
        if (!$this->titles->contains($title)) {
            $this->titles->add($title);
            $title->setCms($this);
        }

        return $this;
    }

    public function removeTitle(CmsTitle $title): static
    {
        if ($this->titles->removeElement($title) && $title->getCms() === $this) {
            $title->setCms(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, CmsLinkName>
     */
    public function getLinkNames(): Collection
    {
        return $this->linkNames;
    }

    public function addLinkName(CmsLinkName $linkName): static
    {
        if (!$this->linkNames->contains($linkName)) {
            $this->linkNames->add($linkName);
            $linkName->setCms($this);
        }

        return $this;
    }

    public function removeLinkName(CmsLinkName $linkName): static
    {
        if ($this->linkNames->removeElement($linkName) && $linkName->getCms() === $this) {
            $linkName->setCms(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, CmsBlock>
     */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function getLanguages(): array
    {
        return array_values(array_unique(
            $this->blocks->map(static fn(CmsBlock $b) => $b->getLanguage())->toArray()
        ));
    }

    public function getLanguageFilteredBlockJsonList(string $language): Collection
    {
        return new ArrayCollection(
            $this->blocks
                ->filter(static fn(CmsBlock $b) => $b->getLanguage() === $language)
                ->map(static fn(CmsBlock $b) => CmsBlockTypes::buildObject(
                    $b->getType(),
                    $b->getJson(),
                    $b->getImage(),
                ))
                ->toArray(),
        );
    }

    public function getPageTitle(string $language): ?string
    {
        return $this->titles->findFirst(static fn(int $k, CmsTitle $t) => $t->getLanguage() === $language)?->getTitle();
    }

    public function getLinkName(string $language): ?string
    {
        return $this->linkNames
            ->findFirst(static fn(int $k, CmsLinkName $l) => $l->getLanguage() === $language)
            ?->getName();
    }

    public function getPageContent(string $language): ?string
    {
        $content = $this->blocks
            ->filter(static fn(CmsBlock $b) => $b->getLanguage() === $language && $b->getType() === CmsBlockTypes::Text)
            ->map(static fn(CmsBlock $b) => TextType::fromJson($b->getJson())->content)
            ->toArray();

        return $content !== [] ? implode("\n\n", $content) : null;
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
