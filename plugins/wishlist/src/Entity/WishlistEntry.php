<?php declare(strict_types=1);

namespace Plugin\Wishlist\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Wishlist\Repository\WishlistEntryRepository;

/**
 * One member's wish for one item of any type. itemId is a plain INT keyed by itemType (no FK
 * to any plugin entity). priorityCounter ages up each time a different item of the same type
 * is picked, so long-ignored wishes float to the top of the demand ranking.
 */
#[ORM\Entity(repositoryClass: WishlistEntryRepository::class)]
#[ORM\Table(name: 'plg_wishlist_entry')]
#[ORM\UniqueConstraint(name: 'uniq_wishlist_user_item', columns: ['user_id', 'item_type', 'item_id'])]
class WishlistEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\Column(length: 50)]
    private ?string $itemType = null;

    #[ORM\Column]
    private ?int $itemId = null;

    #[ORM\Column]
    private int $priorityCounter = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPriorityCounter(): int
    {
        return $this->priorityCounter;
    }

    public function setPriorityCounter(int $priorityCounter): static
    {
        $this->priorityCounter = $priorityCounter;

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
