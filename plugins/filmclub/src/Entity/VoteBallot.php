<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\VoteBallotRepository;

#[ORM\Entity(repositoryClass: VoteBallotRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_member_vote', columns: ['vote_id', 'member_id'])]
class VoteBallot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vote::class, inversedBy: 'ballots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vote $vote = null;

    #[ORM\ManyToOne(targetEntity: Film::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Film $film = null;

    #[ORM\Column]
    private ?int $memberId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVote(): ?Vote
    {
        return $this->vote;
    }

    public function setVote(?Vote $vote): static
    {
        $this->vote = $vote;

        return $this;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(?Film $film): static
    {
        $this->film = $film;

        return $this;
    }

    public function getMemberId(): ?int
    {
        return $this->memberId;
    }

    public function setMemberId(int $memberId): static
    {
        $this->memberId = $memberId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
