<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmPollRepository;

#[ORM\Entity(repositoryClass: FilmPollRepository::class)]
class FilmPoll
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $durationDays = 7;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(enumType: PollStatus::class)]
    private PollStatus $status = PollStatus::Active;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Film $winningFilm = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tiedFilmIds = null;

    #[ORM\OneToMany(targetEntity: FilmPollVote::class, mappedBy: 'poll', cascade: ['remove'])]
    private Collection $votes;

    #[ORM\ManyToMany(targetEntity: Film::class)]
    #[ORM\JoinTable(name: 'film_poll_films')]
    #[ORM\JoinColumn(name: 'poll_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'film_id', onDelete: 'CASCADE')]
    private Collection $films;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
        $this->films = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getEventId(): ?int
    {
        return $this->event?->getId();
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getStatus(): PollStatus
    {
        return $this->status;
    }

    public function setStatus(PollStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getWinningFilm(): ?Film
    {
        return $this->winningFilm;
    }

    public function setWinningFilm(?Film $winningFilm): static
    {
        $this->winningFilm = $winningFilm;

        return $this;
    }

    public function getTiedFilmIds(): ?array
    {
        return $this->tiedFilmIds;
    }

    public function setTiedFilmIds(?array $tiedFilmIds): static
    {
        $this->tiedFilmIds = $tiedFilmIds;

        return $this;
    }

    /** @return Collection<int, FilmPollVote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    /** @return Collection<int, Film> */
    public function getFilms(): Collection
    {
        return $this->films;
    }

    public function addFilm(Film $film): static
    {
        if (!$this->films->contains($film)) {
            $this->films->add($film);
        }

        return $this;
    }

    public function removeFilm(Film $film): static
    {
        $this->films->removeElement($film);

        return $this;
    }
}
