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
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private null|BookPoll $poll = null;

    #[ORM\Column]
    private null|int $userId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|BookSuggestion $suggestion = null;

    #[ORM\Column]
    private null|DateTimeImmutable $votedAt = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getPoll(): null|BookPoll
    {
        return $this->poll;
    }

    public function setPoll(BookPoll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }

    public function getUserId(): null|int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getSuggestion(): null|BookSuggestion
    {
        return $this->suggestion;
    }

    public function setSuggestion(BookSuggestion $suggestion): static
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    public function getVotedAt(): null|DateTimeImmutable
    {
        return $this->votedAt;
    }

    public function setVotedAt(DateTimeImmutable $votedAt): static
    {
        $this->votedAt = $votedAt;

        return $this;
    }
}
