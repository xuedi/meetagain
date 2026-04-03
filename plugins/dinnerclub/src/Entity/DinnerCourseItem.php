<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Repository\DinnerCourseItemRepository;

#[ORM\Entity(repositoryClass: DinnerCourseItemRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_course_dish', columns: ['course_id', 'dish_id'])]
class DinnerCourseItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DinnerCourse $course = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dish $dish = null;

    #[ORM\Column]
    private bool $isPrimary = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?DinnerCourse
    {
        return $this->course;
    }

    public function setCourse(DinnerCourse $course): static
    {
        $this->course = $course;

        return $this;
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

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;

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
}
