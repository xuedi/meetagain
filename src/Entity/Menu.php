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
    private null|int $id = null;

    #[ORM\Column(enumType: MenuLocation::class)]
    private null|MenuLocation $location = null;

    #[ORM\OneToMany(targetEntity: MenuTranslation::class, mappedBy: 'menu')]
    private Collection $translations;

    #[ORM\Column]
    private null|float $priority = null;

    #[ORM\Column(enumType: MenuVisibility::class)]
    private null|MenuVisibility $visibility = null;

    #[ORM\Column(enumType: MenuType::class)]
    private null|MenuType $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $slug = null;

    #[ORM\ManyToOne]
    private null|Cms $cms = null;

    #[ORM\ManyToOne]
    private null|Event $event = null;

    #[ORM\Column(nullable: true, enumType: MenuRoutes::class)]
    private null|MenuRoutes $route = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getSlug(): null|string
    {
        return $this->slug;
    }

    public function setSlug(null|string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLocation(): null|MenuLocation
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

    public function findTranslation(string $language): null|MenuTranslation
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

    public function getName(string $language): string
    {
        return $this->findTranslation($language)?->getName() ?? '';
    }

    public function getPriority(): null|float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getType(): null|MenuType
    {
        return $this->type;
    }

    public function setType(MenuType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCms(): null|Cms
    {
        return $this->cms;
    }

    public function setCms(null|Cms $cms): static
    {
        $this->cms = $cms;

        return $this;
    }

    public function getEvent(): null|Event
    {
        return $this->event;
    }

    public function setEvent(null|Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getVisibility(): null|MenuVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(MenuVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getRoute(): null|MenuRoutes
    {
        return $this->route;
    }

    public function setRoute(null|MenuRoutes $route): static
    {
        $this->route = $route;

        return $this;
    }
}
