<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\ManyToOne(targetEntity: ItemTag::class)]
    #[ORM\JoinColumn(name: 'tag_id', nullable: false, onDelete: 'CASCADE')]
    private ?ItemTag $tag = null;

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

    public function getTag(): ?ItemTag
    {
        return $this->tag;
    }

    public function setTag(ItemTag $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getTagId(): ?int
    {
        return $this->tag?->getId();
    }
}
