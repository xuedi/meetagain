<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Repository\DinnerCourseRepository;

#[ORM\Entity(repositoryClass: DinnerCourseRepository::class)]
class DinnerCourse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dinner $dinner = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\OneToMany(
        targetEntity: DinnerCourseItem::class,
        mappedBy: 'course',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['isPrimary' => 'DESC', 'sortOrder' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDinner(): ?Dinner
    {
        return $this->dinner;
    }

    public function setDinner(Dinner $dinner): static
    {
        $this->dinner = $dinner;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DinnerCourseItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCourse($this);
        }

        return $this;
    }

    public function removeItem(DinnerCourseItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
