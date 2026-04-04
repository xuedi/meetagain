<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Repository\DishImageRepository;

#[ORM\Entity(repositoryClass: DishImageRepository::class)]
#[ORM\Table(name: 'dinnerclub_dish_image')]
#[ORM\UniqueConstraint(name: 'unique_dish_image', columns: ['dish_id', 'image_id'])]
class DishImage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'galleryImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dish $dish = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Image $image = null;

    #[ORM\Column]
    private int $sortOrder = 0;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

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
