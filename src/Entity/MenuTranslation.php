<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(fields: ['language', 'menu'])]
#[ORM\Entity(repositoryClass: MenuTranslationRepository::class)]
class MenuTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private null|Menu $menu = null;

    #[ORM\Column(length: 2)]
    private null|string $language = null;

    #[ORM\Column(length: 255)]
    private null|string $name = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getMenu(): null|Menu
    {
        return $this->menu;
    }

    public function setMenu(null|Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getLanguage(): null|string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getName(): null|string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
