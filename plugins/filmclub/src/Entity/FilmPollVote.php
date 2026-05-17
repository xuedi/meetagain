<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmPollVoteRepository;

#[ORM\Entity(repositoryClass: FilmPollVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_poll_user_film', columns: ['poll_id', 'user_id', 'film_id'])]
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Film $film = null;

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

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(Film $film): static
    {
        $this->film = $film;

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
