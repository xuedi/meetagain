<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dishes\Repository\DishListRepository;

#[ORM\Entity(repositoryClass: DishListRepository::class)]
class DishList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 255)]
    private null|string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private null|string $description = null;

    #[ORM\Column]
    private int $createdBy;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $isPublic = false;

    #[ORM\Column(type: Types::JSON)]
    private array $dishIds = [];

    public function getId(): null|int
    {
        return $this->id;
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

    public function getDescription(): null|string
    {
        return $this->description;
    }

    public function setDescription(null|string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getDishIds(): array
    {
        return $this->dishIds;
    }

    public function setDishIds(array $dishIds): static
    {
        $this->dishIds = $dishIds;

        return $this;
    }

    public function addDishId(int $dishId): static
    {
        if (!in_array($dishId, $this->dishIds, true)) {
            $this->dishIds[] = $dishId;
        }

        return $this;
    }

    public function removeDishId(int $dishId): static
    {
        $this->dishIds = array_values(array_filter(
            $this->dishIds,
            fn(int $id) => $id !== $dishId
        ));

        return $this;
    }

    public function hasDish(int $dishId): bool
    {
        return in_array($dishId, $this->dishIds, true);
    }

    public function getDishCount(): int
    {
        return count($this->dishIds);
    }
}
