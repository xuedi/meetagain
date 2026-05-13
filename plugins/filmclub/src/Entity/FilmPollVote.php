<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmPollVoteRepository;

#[ORM\Entity(repositoryClass: FilmPollVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_poll_user_suggestion', columns: ['poll_id', 'user_id', 'suggestion_id'])]
class FilmPollVote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FilmPoll $poll = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FilmSuggestion $suggestion = null;

    #[ORM\Column]
    private ?DateTimeImmutable $votedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?FilmPoll
    {
        return $this->poll;
    }

    public function setPoll(FilmPoll $poll): static
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

    public function getSuggestion(): ?FilmSuggestion
    {
        return $this->suggestion;
    }

    public function setSuggestion(FilmSuggestion $suggestion): static
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
