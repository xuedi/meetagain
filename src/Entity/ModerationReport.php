<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\ModerationReportReason;
use App\Enum\ModerationReportStatus;
use App\Repository\ModerationReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModerationReportRepository::class)]
#[ORM\Index(name: 'idx_moderation_report_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_moderation_report_status', columns: ['status'])]
class ModerationReport
{
    public const int MAX_REMARKS_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    #[ORM\Column(length: 255)]
    private string $subjectLabel;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\Column(length: 32, enumType: ModerationReportReason::class)]
    private ModerationReportReason $reason;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\Column(length: 16, enumType: ModerationReportStatus::class)]
    private ModerationReportStatus $status = ModerationReportStatus::Open;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $subjectType, int $subjectId, ModerationReportReason $reason, DateTimeImmutable $createdAt)
    {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->reason = $reason;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getSubjectLabel(): string
    {
        return $this->subjectLabel;
    }

    public function setSubjectLabel(string $subjectLabel): static
    {
        $this->subjectLabel = mb_substr($subjectLabel, 0, 255);

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

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

    public function getReason(): ModerationReportReason
    {
        return $this->reason;
    }

    public function getRemarks(): ?string
    {
        return $this->remarks;
    }

    public function setRemarks(?string $remarks): static
    {
        $this->remarks = $remarks;

        return $this;
    }

    public function getStatus(): ModerationReportStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === ModerationReportStatus::Open;
    }

    public function resolve(ModerationReportStatus $status, User $resolvedBy, DateTimeImmutable $resolvedAt): static
    {
        $this->status = $status;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = $resolvedAt;

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
}
