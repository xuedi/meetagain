<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Repository\ChangeProposalRepository;
use App\Review\FieldChange;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity(repositoryClass: ChangeProposalRepository::class)]
#[ORM\Table(name: 'change_proposal')]
#[ORM\Index(name: 'idx_change_proposal_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_change_proposal_status', columns: ['status'])]
class ChangeProposal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $proposedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(length: 10, enumType: ChangeProposalStatus::class)]
    private ChangeProposalStatus $status = ChangeProposalStatus::Pending;

    /** @var array<string, array{before: ?string, after: ?string, resolution: ?string}> */
    #[ORM\Column]
    private array $changes = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getProposedBy(): User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getStatus(): ChangeProposalStatus
    {
        return $this->status;
    }

    public function setStatus(ChangeProposalStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === ChangeProposalStatus::Pending;
    }

    /** @return list<FieldChange> */
    public function getChanges(): array
    {
        $list = [];
        foreach ($this->changes as $field => $data) {
            $list[] = FieldChange::fromArray($field, $data);
        }

        return $list;
    }

    /** @param list<FieldChange> $changes */
    public function setChanges(array $changes): static
    {
        $map = [];
        foreach ($changes as $change) {
            $map[$change->field] = $change->toArray();
        }
        $this->changes = $map;

        return $this;
    }

    public function getChange(string $field): FieldChange
    {
        if (!isset($this->changes[$field])) {
            throw new InvalidArgumentException(sprintf('Unknown field "%s" in proposal', $field));
        }

        return FieldChange::fromArray($field, $this->changes[$field]);
    }

    public function resolveField(string $field, FieldResolution $resolution): static
    {
        if (!isset($this->changes[$field])) {
            throw new InvalidArgumentException(sprintf('Unknown field "%s" in proposal', $field));
        }
        $this->changes[$field]['resolution'] = $resolution->value;

        return $this;
    }

    /** @return list<FieldChange> */
    public function getUnresolvedChanges(): array
    {
        return array_values(array_filter($this->getChanges(), static fn(FieldChange $change): bool => !$change->isResolved()));
    }

    public function isFullyResolved(): bool
    {
        return $this->getUnresolvedChanges() === [];
    }

    public function hasAppliedField(): bool
    {
        return array_any($this->getChanges(), static fn(FieldChange $change): bool => $change->isApplied());
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }
}
