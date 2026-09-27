<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Repository\RequestRepository;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
#[ORM\Table(name: 'mod_circulation_request')]
#[ORM\Index(name: 'idx_circulation_request_queue', columns: ['context', 'item_type', 'item_id', 'status'])]
#[ORM\Index(name: 'idx_circulation_request_user', columns: ['user_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_circulation_request_open', columns: ['context', 'item_type', 'item_id', 'user_id', 'open_slot'])]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private string $context;

    #[ORM\Column(length: 50)]
    private string $itemType;

    #[ORM\Column]
    private int $itemId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private DateTimeImmutable $requestedAt;

    #[ORM\Column(length: 20, enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Waiting;

    #[ORM\Column(nullable: true)]
    private ?int $openSlot = 1;

    #[ORM\ManyToOne(targetEntity: Copy::class)]
    #[ORM\JoinColumn(name: 'offered_copy_id', nullable: true, onDelete: 'SET NULL')]
    private ?Copy $offeredCopy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $offeredAt = null;

    public function __construct(string $context, string $itemType, int $itemId, User $user, DateTimeImmutable $requestedAt)
    {
        $this->context = $context;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->user = $user;
        $this->requestedAt = $requestedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function setStatus(RequestStatus $status): static
    {
        $this->status = $status;
        $this->openSlot = $status->isOpen() ? 1 : null;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->openSlot !== null;
    }

    public function getOfferedCopy(): ?Copy
    {
        return $this->offeredCopy;
    }

    public function setOfferedCopy(?Copy $offeredCopy): static
    {
        $this->offeredCopy = $offeredCopy;

        return $this;
    }

    public function getOfferedAt(): ?DateTimeImmutable
    {
        return $this->offeredAt;
    }

    public function setOfferedAt(?DateTimeImmutable $offeredAt): static
    {
        $this->offeredAt = $offeredAt;

        return $this;
    }
}
