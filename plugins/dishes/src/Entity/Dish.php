<?php

namespace Plugin\Dishes\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishRepository;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(targetEntity: DishTranslation::class, mappedBy: 'dish')]
    private Collection $translations;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\ManyToOne]
    private ?Image $previewImage = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
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
}
