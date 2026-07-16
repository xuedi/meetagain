<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishLikeRepository;

#[ORM\Entity(repositoryClass: DishLikeRepository::class)]
#[ORM\Table(name: 'dishes_dish_like')]
#[ORM\UniqueConstraint(name: 'uniq_dishes_dish_user_like', columns: ['dish_id', 'user_id'])]
class DishLike
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dish $dish = null;

    #[ORM\Column]
    private ?int $userId = null;

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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }
}
