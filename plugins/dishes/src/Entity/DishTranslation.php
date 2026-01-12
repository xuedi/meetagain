<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishTranslationRepository;

#[ORM\UniqueConstraint(fields: ['language', 'dish'])]
#[ORM\Entity(repositoryClass: DishTranslationRepository::class)]
class DishTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private null|Dish $dish = null;

    #[ORM\Column(length: 2)]
    private null|string $language = null;

    #[ORM\Column(length: 255)]
    private null|string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $phonetic = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private null|string $recipe = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getDish(): null|Dish
    {
        return $this->dish;
    }

    public function setDish(null|Dish $dish): static
    {
        $this->dish = $dish;

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

    public function getPhonetic(): null|string
    {
        return $this->phonetic;
    }

    public function setPhonetic(null|string $phonetic): static
    {
        $this->phonetic = $phonetic;

        return $this;
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRecipe(): null|string
    {
        return $this->recipe;
    }

    public function setRecipe(null|string $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }
}
