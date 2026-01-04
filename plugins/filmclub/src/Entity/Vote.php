<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\VoteRepository;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
class Vote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $eventId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column]
    private bool $isClosed = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    /** @var Collection<int, VoteBallot> */
    #[ORM\OneToMany(targetEntity: VoteBallot::class, mappedBy: 'vote', cascade: ['persist', 'remove'])]
    private Collection $ballots;

    public function __construct()
    {
        $this->ballots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): static
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(\DateTimeImmutable $closesAt): static
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): static
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function isVotingOpen(): bool
    {
        if ($this->isClosed) {
            return false;
        }

        return $this->closesAt > new \DateTimeImmutable();
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /** @return Collection<int, VoteBallot> */
    public function getBallots(): Collection
    {
        return $this->ballots;
    }

    public function addBallot(VoteBallot $ballot): static
    {
        if (!$this->ballots->contains($ballot)) {
            $this->ballots->add($ballot);
            $ballot->setVote($this);
        }

        return $this;
    }

    public function getWinningFilm(): ?Film
    {
        if ($this->ballots->isEmpty()) {
            return null;
        }

        $filmCounts = [];
        foreach ($this->ballots as $ballot) {
            $filmId = $ballot->getFilm()?->getId();
            if ($filmId !== null) {
                $filmCounts[$filmId] = ($filmCounts[$filmId] ?? 0) + 1;
            }
        }

        if (empty($filmCounts)) {
            return null;
        }

        $winningFilmId = array_keys($filmCounts, max($filmCounts))[0];

        foreach ($this->ballots as $ballot) {
            if ($ballot->getFilm()?->getId() === $winningFilmId) {
                return $ballot->getFilm();
            }
        }

        return null;
    }
}
