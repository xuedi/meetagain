<?php declare(strict_types=1);

namespace Plugin\Fediverse\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Fediverse\Repository\DeliveryRepository;

#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
#[ORM\Table(name: 'plg_fediverse_delivery')]
#[ORM\Index(name: 'idx_fediverse_delivery_due', columns: ['due_at'])]
#[ORM\Index(name: 'idx_fediverse_delivery_claim', columns: ['claim_token'])]
class Delivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: Follower::MAX_URL_LENGTH)]
    private string $inbox;

    #[ORM\Column(type: Types::TEXT)]
    private string $activity;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column]
    private DateTimeImmutable $dueAt;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $claimToken = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $claimedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Account $account, string $inbox, string $activity, DateTimeImmutable $createdAt)
    {
        $this->account = $account;
        $this->inbox = $inbox;
        $this->activity = $activity;
        $this->dueAt = $createdAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getInbox(): string
    {
        return $this->inbox;
    }

    public function getActivity(): string
    {
        return $this->activity;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getDueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function isClaimed(): bool
    {
        return $this->claimToken !== null && $this->claimedAt !== null;
    }

    public function retryAt(DateTimeImmutable $dueAt): static
    {
        ++$this->attempts;
        $this->dueAt = $dueAt;
        $this->claimToken = null;
        $this->claimedAt = null;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
