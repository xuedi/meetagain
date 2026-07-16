<?php declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * The universal event-to-item link. itemType is a registry key and itemId is a plain INT
 * (no FK to any plugin entity). Multiple associations per event are allowed - a menu of
 * several items, or a multi-item event, is several rows.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_event_item', columns: ['event_id', 'item_type', 'item_id'])]
class EventItemAssociation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(length: 50)]
    private ?string $itemType = null;

    #[ORM\Column]
    private ?int $itemId = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sectionLabel = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;

        return $this;
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSectionLabel(): ?string
    {
        return $this->sectionLabel;
    }

    public function setSectionLabel(?string $sectionLabel): static
    {
        $this->sectionLabel = $sectionLabel;

        return $this;
    }
}
