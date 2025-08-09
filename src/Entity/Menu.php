<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(enumType: MenuLocation::class)]
    private ?MenuLocation $location = null;

    #[ORM\OneToMany(targetEntity: MenuTranslation::class, mappedBy: 'menu')]
    private Collection $translations;

    #[ORM\Column]
    private ?float $priority = null;

    #[ORM\Column]
    private array $visibility = [];

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLocation(): ?MenuLocation
    {
        return $this->location;
    }

    public function setLocation(MenuLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getTranslation(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(MenuTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setMenu($this);
        }

        return $this;
    }

    public function removeTranslation(MenuTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getMenu() === $this) {
            $translation->setMenu(null);
        }

        return $this;
    }

    public function findTranslation(string $language): ?MenuTranslation
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

    public function getPriority(): ?float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getVisibility(): array
    {
        return $this->visibility;
    }

    public function setVisibility(array $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }
}
