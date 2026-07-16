<?php declare(strict_types=1);

namespace Plugin\Voting\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Voting\Repository\VoteRepository;

/**
 * One user's approval of one candidate item on a poll. The unique (poll, user, item)
 * constraint makes a vote idempotent; a user's full set of rows is their approval ballot.
 */
#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_vote_poll_user_item', columns: ['poll_id', 'user_id', 'item_id'])]
class Vote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\Column]
    private ?int $itemId = null;

    #[ORM\Column]
    private ?DateTimeImmutable $votedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(Poll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getVotedAt(): ?DateTimeImmutable
    {
        return $this->votedAt;
    }

    public function setVotedAt(DateTimeImmutable $votedAt): static
    {
        $this->votedAt = $votedAt;

        return $this;
    }
}
