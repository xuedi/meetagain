<?php declare(strict_types=1);

namespace Plugin\Fediverse\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Fediverse\Repository\FollowerRepository;

#[ORM\Entity(repositoryClass: FollowerRepository::class)]
#[ORM\Table(name: 'plg_fediverse_follower')]
#[ORM\UniqueConstraint(name: 'uniq_fediverse_follower_account_actor', columns: ['account_id', 'actor_id'])]
#[ORM\Index(name: 'idx_fediverse_follower_actor', columns: ['actor_id'])]
class Follower
{
    public const int MAX_URL_LENGTH = 512;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: self::MAX_URL_LENGTH)]
    private string $actorId;

    #[ORM\Column(length: self::MAX_URL_LENGTH)]
    private string $inbox;

    #[ORM\Column(length: self::MAX_URL_LENGTH, nullable: true)]
    private ?string $sharedInbox;

    #[ORM\Column(length: self::MAX_URL_LENGTH)]
    private string $followId;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Account $account, string $actorId, string $inbox, ?string $sharedInbox, string $followId, DateTimeImmutable $createdAt)
    {
        $this->account = $account;
        $this->actorId = $actorId;
        $this->inbox = $inbox;
        $this->sharedInbox = $sharedInbox;
        $this->followId = $followId;
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

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getInbox(): string
    {
        return $this->inbox;
    }

    public function getSharedInbox(): ?string
    {
        return $this->sharedInbox;
    }

    public function getFollowId(): string
    {
        return $this->followId;
    }

    public function refollow(string $inbox, ?string $sharedInbox, string $followId): static
    {
        $this->inbox = $inbox;
        $this->sharedInbox = $sharedInbox;
        $this->followId = $followId;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
