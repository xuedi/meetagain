<?php declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 20, enumType: AnnouncementStatus::class)]
    private AnnouncementStatus $status = AnnouncementStatus::Draft;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $recipientCount = null;

    #[ORM\ManyToOne]
    private ?Cms $cmsPage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): AnnouncementStatus
    {
        return $this->status;
    }

    public function setStatus(AnnouncementStatus $status): static
    {
        $this->status = $status;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getRecipientCount(): ?int
    {
        return $this->recipientCount;
    }

    public function setRecipientCount(?int $recipientCount): static
    {
        $this->recipientCount = $recipientCount;

        return $this;
    }

    public function getCmsPage(): ?Cms
    {
        return $this->cmsPage;
    }

    public function setCmsPage(?Cms $cmsPage): static
    {
        $this->cmsPage = $cmsPage;

        return $this;
    }

    public function isDraft(): bool
    {
        return $this->status === AnnouncementStatus::Draft;
    }

    public function isSent(): bool
    {
        return $this->status === AnnouncementStatus::Sent;
    }
}
