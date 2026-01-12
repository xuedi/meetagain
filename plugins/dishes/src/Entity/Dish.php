<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishRepository;

#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\OneToMany(targetEntity: DishTranslation::class, mappedBy: 'dish', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private null|int $createdBy = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\ManyToOne]
    private null|Image $previewImage = null;

    #[ORM\Column(length: 2, nullable: true)]
    private null|string $originLang = null;

    #[ORM\Column]
    private int $likes = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private null|array $suggestions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $origin = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): null|int
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

    public function findTranslation(string $language): null|DishTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }
        return null;
    }

    public function getTranslatedName(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation->getName() ?? '';
            }
        }
        return '';
    }

    public function getTranslatedDescription(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation->getDescription() ?? '';
            }
        }
        return '';
    }

    public function getPhonetic(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation->getPhonetic() ?? '';
            }
        }
        return '';
    }

    public function getCreatedAt(): null|DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): null|int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(null|int $createdBy): static
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

    public function getPreviewImage(): null|Image
    {
        return $this->previewImage;
    }

    public function setPreviewImage(null|Image $previewImage): static
    {
        $this->previewImage = $previewImage;

        return $this;
    }

    public function getOriginLang(): null|string
    {
        return $this->originLang;
    }

    public function setOriginLang(null|string $originLang): static
    {
        $this->originLang = $originLang;

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

    public function getSuggestions(): null|array
    {
        return $this->suggestions;
    }

    public function setSuggestions(null|array $suggestions): static
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
            fn(array $s) => DishSuggestion::fromJson($s)->getHash() !== $hash
        ));

        if ($this->suggestions === []) {
            $this->suggestions = null;
        }

        return $this;
    }

    public function findSuggestionByHash(string $hash): null|DishSuggestion
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

        return array_map(
            fn(array $s) => DishSuggestion::fromJson($s),
            $this->suggestions
        );
    }

    public function hasSuggestions(): bool
    {
        return $this->suggestions !== null && $this->suggestions !== [];
    }

    public function getOrigin(): null|string
    {
        return $this->origin;
    }

    public function setOrigin(null|string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getTranslatedRecipe(string $language): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation->getRecipe() ?? '';
            }
        }
        return '';
    }
}
