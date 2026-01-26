<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookRepository;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 17, unique: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $pageCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $publishedYear = null;

    #[ORM\ManyToOne]
    private ?Image $coverImage = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    /** @var Collection<int, BookSuggestion> */
    #[ORM\OneToMany(targetEntity: BookSuggestion::class, mappedBy: 'book')]
    private Collection $suggestions;

    /** @var Collection<int, BookSelection> */
    #[ORM\OneToMany(targetEntity: BookSelection::class, mappedBy: 'book')]
    private Collection $selections;

    /** @var Collection<int, BookNote> */
    #[ORM\OneToMany(targetEntity: BookNote::class, mappedBy: 'book')]
    private Collection $notes;

    public function __construct()
    {
        $this->suggestions = new ArrayCollection();
        $this->selections = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

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

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): static
    {
        $this->pageCount = $pageCount;

        return $this;
    }

    public function getPublishedYear(): ?int
    {
        return $this->publishedYear;
    }

    public function setPublishedYear(?int $publishedYear): static
    {
        $this->publishedYear = $publishedYear;

        return $this;
    }

    public function getCoverImage(): ?Image
    {
        return $this->coverImage;
    }

    public function setCoverImage(?Image $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

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

    /** @return Collection<int, BookSuggestion> */
    public function getSuggestions(): Collection
    {
        return $this->suggestions;
    }

    /** @return Collection<int, BookSelection> */
    public function getSelections(): Collection
    {
        return $this->selections;
    }

    /** @return Collection<int, BookNote> */
    public function getNotes(): Collection
    {
        return $this->notes;
    }
}
