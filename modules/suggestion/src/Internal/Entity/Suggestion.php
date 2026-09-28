<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Internal\Repository\SuggestionRepository;

#[ORM\Entity(repositoryClass: SuggestionRepository::class)]
#[ORM\Table(name: 'mod_suggestion')]
#[ORM\Index(name: 'idx_suggestion_target_type', columns: ['target_type'])]
#[ORM\Index(name: 'idx_suggestion_status', columns: ['status'])]
class Suggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $targetType;

    /** @var array<string, scalar|null> */
    #[ORM\Column]
    private array $payload = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $proposedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(length: 10, enumType: Status::class)]
    private Status $status = Status::Pending;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdId = null;

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

    /** @return array<string, scalar|null> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, scalar|null> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

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

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === Status::Pending;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getCreatedId(): ?int
    {
        return $this->createdId;
    }

    public function setCreatedId(?int $createdId): static
    {
        $this->createdId = $createdId;

        return $this;
    }
}
