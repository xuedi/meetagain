<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\ItemReportReason;
use App\Enum\ItemReportRelationship;
use App\Enum\ItemReportStatus;
use App\Repository\ItemReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemReportRepository::class)]
#[ORM\Index(name: 'idx_item_report_item', columns: ['item_type', 'item_id', 'status'])]
class ItemReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $itemType;

    #[ORM\Column]
    private int $itemId;

    #[ORM\Column(length: 255)]
    private string $itemLabel;

    #[ORM\Column(length: 20, enumType: ItemReportReason::class)]
    private ItemReportReason $reason;

    #[ORM\Column(length: 20, enumType: ItemReportRelationship::class)]
    private ItemReportRelationship $relationship;

    #[ORM\Column(type: Types::TEXT)]
    private string $explanation;

    #[ORM\Column(length: 255)]
    private string $notifierName;

    #[ORM\Column(length: 180)]
    private string $notifierEmail;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\Column]
    private bool $goodFaith = false;

    #[ORM\Column(length: 10, enumType: ItemReportStatus::class)]
    private ItemReportStatus $status = ItemReportStatus::Open;

    #[ORM\Column(length: 5)]
    private string $locale = 'en';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getItemLabel(): string
    {
        return $this->itemLabel;
    }

    public function setItemLabel(string $itemLabel): static
    {
        $this->itemLabel = mb_substr($itemLabel, 0, 255);

        return $this;
    }

    public function getReason(): ItemReportReason
    {
        return $this->reason;
    }

    public function setReason(ItemReportReason $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getRelationship(): ItemReportRelationship
    {
        return $this->relationship;
    }

    public function setRelationship(ItemReportRelationship $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function setExplanation(string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getNotifierName(): string
    {
        return $this->notifierName;
    }

    public function setNotifierName(string $notifierName): static
    {
        $this->notifierName = $notifierName;

        return $this;
    }

    public function getNotifierEmail(): string
    {
        return $this->notifierEmail;
    }

    public function setNotifierEmail(string $notifierEmail): static
    {
        $this->notifierEmail = $notifierEmail;

        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function isGoodFaith(): bool
    {
        return $this->goodFaith;
    }

    public function setGoodFaith(bool $goodFaith): static
    {
        $this->goodFaith = $goodFaith;

        return $this;
    }

    public function getStatus(): ItemReportStatus
    {
        return $this->status;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOpen(): bool
    {
        return $this->status === ItemReportStatus::Open;
    }

    public function resolve(ItemReportStatus $status, ?User $resolvedBy, DateTimeImmutable $at): static
    {
        $this->status = $status;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = $at;

        return $this;
    }
}
