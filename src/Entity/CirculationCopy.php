<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\CirculationCopyStatus;
use App\Repository\CirculationCopyRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CirculationCopyRepository::class)]
#[ORM\Table(name: 'circulation_copy')]
#[ORM\Index(name: 'idx_circulation_copy_item', columns: ['context', 'item_type', 'item_id'])]
#[ORM\Index(name: 'idx_circulation_copy_status', columns: ['context', 'item_type', 'status'])]
#[ORM\Index(name: 'idx_circulation_copy_holder', columns: ['holder_id'])]
class CirculationCopy
{
    public const int MAX_LABEL_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $itemType;

    #[ORM\Column]
    private int $itemId;

    #[ORM\Column(length: 191)]
    private string $context;

    #[ORM\Column(length: self::MAX_LABEL_LENGTH, nullable: true)]
    private ?string $label = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'donated_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $donatedBy = null;

    #[ORM\Column]
    private DateTimeImmutable $donatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'holder_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $holder = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $heldSince = null;

    #[ORM\Column(length: 20, enumType: CirculationCopyStatus::class)]
    private CirculationCopyStatus $status = CirculationCopyStatus::Available;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(string $context, string $itemType, int $itemId, DateTimeImmutable $donatedAt)
    {
        $this->context = $context;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->donatedAt = $donatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDonatedBy(): ?User
    {
        return $this->donatedBy;
    }

    public function setDonatedBy(?User $donatedBy): static
    {
        $this->donatedBy = $donatedBy;

        return $this;
    }

    public function getDonatedAt(): DateTimeImmutable
    {
        return $this->donatedAt;
    }

    public function getHolder(): ?User
    {
        return $this->holder;
    }

    public function setHolder(?User $holder): static
    {
        $this->holder = $holder;

        return $this;
    }

    public function getHeldSince(): ?DateTimeImmutable
    {
        return $this->heldSince;
    }

    public function setHeldSince(?DateTimeImmutable $heldSince): static
    {
        $this->heldSince = $heldSince;

        return $this;
    }

    public function getStatus(): CirculationCopyStatus
    {
        return $this->status;
    }

    public function setStatus(CirculationCopyStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function isHeldBy(?User $user): bool
    {
        return $user !== null && $this->holder?->getId() === $user->getId();
    }
}
