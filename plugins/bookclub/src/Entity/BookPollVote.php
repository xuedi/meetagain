<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookPollVoteRepository;

#[ORM\Entity(repositoryClass: BookPollVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['poll_id', 'user_id'])]
class BookPollVote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BookPoll $poll = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?BookSuggestion $suggestion = null;

    #[ORM\Column]
    private ?DateTimeImmutable $votedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?BookPoll
    {
        return $this->poll;
    }

    public function setPoll(BookPoll $poll): static
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

    public function getSuggestion(): ?BookSuggestion
    {
        return $this->suggestion;
    }

    public function setSuggestion(BookSuggestion $suggestion): static
    {
        $this->suggestion = $suggestion;

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
