<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use App\Entity\Image;
use App\Entity\PronunciationSystem;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Repository\DishRepository;

#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(
        targetEntity: DishTranslation::class,
        mappedBy: 'dish',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $translations;

    #[ORM\OneToMany(
        targetEntity: DishImage::class,
        mappedBy: 'dish',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'createdAt' => 'ASC'])]
    private Collection $galleryImages;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\ManyToOne]
    private ?Image $previewImage = null;

    #[ORM\ManyToOne]
    private ?PronunciationSystem $pronunciationSystem = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phonetic = null;

    #[ORM\Column]
    private int $likes = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $suggestions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->galleryImages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslation(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(DishTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setDish($this);
        }

        return $this;
    }

    public function removeTranslation(DishTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getDish() === $this) {
            $translation->setDish(null);
        }

        return $this;
    }

    public function findTranslation(string $language): ?DishTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() !== $language) {
                continue;
            }

            return $translation;
        }
        return null;
    }

    public function getTranslatedName(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() !== $language) {
                continue;
            }

            return $translation->getName() ?? '';
        }
        return '';
    }

    public function getTranslatedDescription(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() !== $language) {
                continue;
            }

            return $translation->getDescription() ?? '';
        }
        return '';
    }

    public function getPhonetic(): ?string
    {
        return $this->phonetic;
    }

    public function setPhonetic(?string $phonetic): static
    {
        $this->phonetic = $phonetic;

        return $this;
    }

    public function getPronunciationSystem(): ?PronunciationSystem
    {
        return $this->pronunciationSystem;
    }

    public function setPronunciationSystem(?PronunciationSystem $pronunciationSystem): static
    {
        $this->pronunciationSystem = $pronunciationSystem;

        return $this;
    }

    public function getAnyTranslatedName(): string
    {
        $first = $this->translations->first();

        return $first !== false ? ($first->getName() ?? '') : '';
    }

    public function getAnyTranslatedDescription(): string
    {
        $first = $this->translations->first();

        return $first !== false ? ($first->getDescription() ?? '') : '';
    }

    public function getAnyTranslatedRecipe(): string
    {
        $first = $this->translations->first();

        return $first !== false ? ($first->getRecipe() ?? '') : '';
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getPreviewImage(): ?Image
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?Image $previewImage): static
    {
        $this->previewImage = $previewImage;

        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;

        return $this;
    }

    public function incrementLikes(): static
    {
        $this->likes++;

        return $this;
    }

    public function getSuggestions(): ?array
    {
        return $this->suggestions;
    }

    public function setSuggestions(?array $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    public function addSuggestion(DishSuggestion $suggestion): static
    {
        $suggestions = $this->suggestions ?? [];
        $suggestions[] = $suggestion->jsonSerialize();
        $this->suggestions = $suggestions;

        return $this;
    }

    public function removeSuggestionByHash(string $hash): static
    {
        if ($this->suggestions === null) {
            return $this;
        }

        $this->suggestions = array_values(array_filter(
            $this->suggestions,
            static fn(array $s) => DishSuggestion::fromJson($s)->getHash() !== $hash,
        ));

        if ($this->suggestions === []) {
            $this->suggestions = null;
        }

        return $this;
    }

    public function findSuggestionByHash(string $hash): ?DishSuggestion
    {
        if ($this->suggestions === null) {
            return null;
        }

        foreach ($this->suggestions as $s) {
            $suggestion = DishSuggestion::fromJson($s);
            if ($suggestion->getHash() === $hash) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * @return DishSuggestion[]
     */
    public function getSuggestionObjects(): array
    {
        if ($this->suggestions === null) {
            return [];
        }

        return array_map(DishSuggestion::fromJson(...), $this->suggestions);
    }

    public function hasSuggestions(): bool
    {
        return $this->suggestions !== null && $this->suggestions !== [];
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getTranslatedRecipe(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() !== $language) {
                continue;
            }

            return $translation->getRecipe() ?? '';
        }
        return '';
    }

    public function getGalleryImages(): Collection
    {
        return $this->galleryImages;
    }

    public function addGalleryImage(DishImage $dishImage): static
    {
        if (!$this->galleryImages->contains($dishImage)) {
            $this->galleryImages->add($dishImage);
            $dishImage->setDish($this);
        }

        return $this;
    }

    public function removeGalleryImage(DishImage $dishImage): static
    {
        $this->galleryImages->removeElement($dishImage);

        return $this;
    }

    /**
     * @return DishImage[]
     */
    public function getVisibleGalleryImages(): array
    {
        return array_values(array_filter(
            $this->galleryImages->toArray(),
            static fn(DishImage $di) => $di->getImage()?->getReported() === null,
        ));
    }
}
