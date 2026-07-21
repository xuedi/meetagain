<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ItemCategoryAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The single category assigned to one item. itemType is a registry key and itemId is a plain INT
 * (no FK to any plugin entity, mirroring EventItemAssociation); categoryId references a definition
 * id living in the plugin Config JSON, so it is a plain INT too. Unique per (itemType, itemId) -
 * an item carries at most one category.
 */
#[ORM\Entity(repositoryClass: ItemCategoryAssignmentRepository::class)]
#[ORM\Table(name: 'item_category_assignment')]
#[ORM\UniqueConstraint(name: 'uniq_item_category', columns: ['item_type', 'item_id'])]
class ItemCategoryAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $itemType = null;

    #[ORM\Column]
    private ?int $itemId = null;

    #[ORM\Column]
    private ?int $categoryId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): static
    {
        $this->categoryId = $categoryId;

        return $this;
    }
}
