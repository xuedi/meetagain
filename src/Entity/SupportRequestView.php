<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportRequestViewRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportRequestViewRepository::class)]
#[ORM\Index(name: 'idx_support_request_view_seen', columns: ['support_request_id', 'seen_at'])]
class SupportRequestView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SupportRequest $supportRequest;

    #[ORM\Column]
    private DateTimeImmutable $seenAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupportRequest(): SupportRequest
    {
        return $this->supportRequest;
    }

    public function setSupportRequest(SupportRequest $supportRequest): static
    {
        $this->supportRequest = $supportRequest;

        return $this;
    }

    public function getSeenAt(): DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function setSeenAt(DateTimeImmutable $seenAt): static
    {
        $this->seenAt = $seenAt;

        return $this;
    }
}
