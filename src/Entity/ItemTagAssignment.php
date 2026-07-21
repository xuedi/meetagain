<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One tag assigned to one item; an item with several tags is several rows. itemType is a registry
 * key and itemId is a plain INT (no FK to any plugin entity); tagId references a definition id in
 * the plugin Config JSON (plain INT). Unique per (itemType, itemId, tagId); the (itemType, tagId)
 * index serves the tag-cloud filter query.
 */
#[ORM\Entity(repositoryClass: ItemTagAssignmentRepository::class)]
#[ORM\Table(name: 'item_tag_assignment')]
#[ORM\UniqueConstraint(name: 'uniq_item_tag', columns: ['item_type', 'item_id', 'tag_id'])]
#[ORM\Index(name: 'idx_item_tag_cloud', columns: ['item_type', 'tag_id'])]
class ItemTagAssignment
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
    private ?int $tagId = null;

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

    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    public function setTagId(int $tagId): static
    {
        $this->tagId = $tagId;

        return $this;
    }
}
