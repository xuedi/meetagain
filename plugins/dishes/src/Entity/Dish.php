<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use App\Entity\Image;
use App\Entity\PronunciationSystem;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishRepository;

#[ORM\Entity(repositoryClass: DishRepository::class)]
#[ORM\Table(name: 'plg_dishes_dish')]
class Dish
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, DishTranslation> */
    #[ORM\OneToMany(targetEntity: DishTranslation::class, mappedBy: 'dish', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, DishImage> */
    #[ORM\OneToMany(targetEntity: DishImage::class, mappedBy: 'dish', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'createdAt' => 'ASC'])]
    private Collection $galleryImages;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Image $previewImage = null;

    #[ORM\ManyToOne]
    private ?PronunciationSystem $pronunciationSystem = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phonetic = null;

    #[ORM\Column]
    private int $likes = 0;

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

    /** @return Collection<int, DishTranslation> */
    public function getTranslations(): Collection
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
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }

        return null;
    }

    public function getTranslatedName(string $language): string
    {
        return $this->findTranslation($language)?->getName() ?? $this->getAnyTranslatedName();
    }

    public function getTranslatedDescription(string $language): string
    {
        return $this->findTranslation($language)?->getDescription() ?? '';
    }

    public function getTranslatedRecipe(string $language): string
    {
        return $this->findTranslation($language)?->getRecipe() ?? '';
    }

    public function getAnyTranslatedName(): string
    {
        $first = $this->translations->first();

        return $first !== false ? $first->getName() ?? '' : '';
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

    public function getPreviewImage(): ?Image
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?Image $previewImage): static
    {
        $this->previewImage = $previewImage;

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

    public function getPhonetic(): ?string
    {
        return $this->phonetic;
    }

    public function setPhonetic(?string $phonetic): static
    {
        $this->phonetic = $phonetic;

        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = max(0, $likes);

        return $this;
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

    /** @return Collection<int, DishImage> */
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

    /** @return DishImage[] gallery images whose underlying image is not reported */
    public function getVisibleGalleryImages(): array
    {
        return array_values(array_filter($this->galleryImages->toArray(), static fn(DishImage $di) => $di->getImage()?->getReported() === null));
    }
}
