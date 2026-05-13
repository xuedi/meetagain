<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmRepository;

#[ORM\Entity(repositoryClass: FilmRepository::class)]
class Film
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalTitle = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(nullable: true)]
    private ?int $runtime = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(enumType: ExternalSource::class, nullable: true)]
    private ?ExternalSource $externalSource = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    #[ORM\ManyToOne]
    private ?Image $posterImage = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: FilmSuggestion::class, mappedBy: 'film')]
    private Collection $suggestions;

    #[ORM\OneToMany(targetEntity: FilmNote::class, mappedBy: 'film')]
    private Collection $notes;

    #[ORM\OneToMany(targetEntity: FilmWishlistEntry::class, mappedBy: 'film')]
    private Collection $wishlistEntries;

    public function __construct()
    {
        $this->suggestions = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->wishlistEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOriginalTitle(): ?string
    {
        return $this->originalTitle;
    }

    public function setOriginalTitle(?string $originalTitle): static
    {
        $this->originalTitle = $originalTitle;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function setRuntime(?int $runtime): static
    {
        $this->runtime = $runtime;

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

    public function getExternalSource(): ?ExternalSource
    {
        return $this->externalSource;
    }

    public function setExternalSource(?ExternalSource $externalSource): static
    {
        $this->externalSource = $externalSource;

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

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function setGenres(array $genres): static
    {
        $this->genres = $genres;

        return $this;
    }

    public function getPosterImage(): ?Image
    {
        return $this->posterImage;
    }

    public function setPosterImage(?Image $posterImage): static
    {
        $this->posterImage = $posterImage;

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

    /** @return Collection<int, FilmSuggestion> */
    public function getSuggestions(): Collection
    {
        return $this->suggestions;
    }

    /** @return Collection<int, FilmNote> */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    /** @return Collection<int, FilmWishlistEntry> */
    public function getWishlistEntries(): Collection
    {
        return $this->wishlistEntries;
    }
}
