<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Enum\DishImageSuggestionType;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;

#[ORM\Entity(repositoryClass: DishImageSuggestionRepository::class)]
#[ORM\Table(name: 'dinnerclub_dish_image_suggestion')]
class DishImageSuggestion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dish $dish = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Image $image = null;

    #[ORM\Column(enumType: DishImageSuggestionType::class)]
    private ?DishImageSuggestionType $type = null;

    #[ORM\Column]
    private ?int $suggestedBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDish(): ?Dish
    {
        return $this->dish;
    }

    public function setDish(Dish $dish): static
    {
        $this->dish = $dish;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getType(): ?DishImageSuggestionType
    {
        return $this->type;
    }

    public function setType(DishImageSuggestionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSuggestedBy(): ?int
    {
        return $this->suggestedBy;
    }

    public function setSuggestedBy(int $suggestedBy): static
    {
        $this->suggestedBy = $suggestedBy;

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
}
