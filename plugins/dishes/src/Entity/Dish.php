<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishRepository;

#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\OneToMany(targetEntity: DishTranslation::class, mappedBy: 'dish')]
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
}
