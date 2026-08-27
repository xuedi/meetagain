<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\CirculationHandoverStatus;
use App\Repository\CirculationHandoverRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CirculationHandoverRepository::class)]
#[ORM\Table(name: 'circulation_handover')]
#[ORM\Index(name: 'idx_circulation_handover_status', columns: ['status', 'opened_at'])]
#[ORM\Index(name: 'idx_circulation_handover_copy', columns: ['copy_id'])]
class CirculationHandover
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CirculationCopy::class)]
    #[ORM\JoinColumn(name: 'copy_id', nullable: false, onDelete: 'CASCADE')]
    private CirculationCopy $copy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'from_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $fromUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'to_user_id', nullable: false, onDelete: 'CASCADE')]
    private User $toUser;

    #[ORM\ManyToOne(targetEntity: CirculationRequest::class)]
    #[ORM\JoinColumn(name: 'request_id', nullable: true, onDelete: 'SET NULL')]
    private ?CirculationRequest $request = null;

    #[ORM\Column]
    private DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $fromConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $toConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'cancelled_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(length: 20, enumType: CirculationHandoverStatus::class)]
    private CirculationHandoverStatus $status = CirculationHandoverStatus::Open;

    public function __construct(CirculationCopy $copy, ?User $fromUser, User $toUser, DateTimeImmutable $openedAt)
    {
        $this->copy = $copy;
        $this->fromUser = $fromUser;
        $this->toUser = $toUser;
        $this->openedAt = $openedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCopy(): CirculationCopy
    {
        return $this->copy;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function getToUser(): User
    {
        return $this->toUser;
    }

    public function getRequest(): ?CirculationRequest
    {
        return $this->request;
    }

    public function setRequest(?CirculationRequest $request): static
    {
        $this->request = $request;

        return $this;
    }

    public function getOpenedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getFromConfirmedAt(): ?DateTimeImmutable
    {
        return $this->fromConfirmedAt;
    }

    public function setFromConfirmedAt(?DateTimeImmutable $fromConfirmedAt): static
    {
        $this->fromConfirmedAt = $fromConfirmedAt;

        return $this;
    }

    public function getToConfirmedAt(): ?DateTimeImmutable
    {
        return $this->toConfirmedAt;
    }

    public function setToConfirmedAt(?DateTimeImmutable $toConfirmedAt): static
    {
        $this->toConfirmedAt = $toConfirmedAt;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;

        return $this;
    }

    public function getStatus(): CirculationHandoverStatus
    {
        return $this->status;
    }

    public function setStatus(CirculationHandoverStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isParticipant(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->fromUser?->getId() === $user->getId() || $this->toUser->getId() === $user->getId();
    }

    public function hasConfirmed(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $isGiver = $this->fromUser?->getId() === $user->getId();
        $isReceiver = $this->toUser->getId() === $user->getId();

        return ($isGiver && $this->fromConfirmedAt !== null) || ($isReceiver && $this->toConfirmedAt !== null);
    }
}
