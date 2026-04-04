<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\ImageReportReason;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageReportRepository::class)]
class ImageReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Image $image = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\Column(enumType: ImageReportReason::class)]
    private ImageReportReason $reason;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\Column(length: 10, enumType: ImageReportStatus::class)]
    private ImageReportStatus $status = ImageReportStatus::Open;

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

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): static
    {
        $this->image = $image;

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

    public function getReason(): ImageReportReason
    {
        return $this->reason;
    }

    public function setReason(ImageReportReason $reason): static
    {
        $this->reason = $reason;

        return $this;
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

    public function getStatus(): ImageReportStatus
    {
        return $this->status;
    }

    public function setStatus(ImageReportStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOpen(): bool
    {
        return $this->status === ImageReportStatus::Open;
    }

    public function isResolved(): bool
    {
        return $this->status === ImageReportStatus::Resolved;
    }
}
