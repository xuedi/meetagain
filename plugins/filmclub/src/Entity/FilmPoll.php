<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

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

    #[ORM\Column]
    private ?int $eventId = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(enumType: PollStatus::class)]
    private PollStatus $status = PollStatus::Active;

    #[ORM\ManyToOne]
    private ?FilmSuggestion $winningSuggestion = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tiedSuggestions = null;

    #[ORM\OneToMany(targetEntity: FilmSuggestion::class, mappedBy: 'poll')]
    private Collection $suggestions;

    #[ORM\OneToMany(targetEntity: FilmPollVote::class, mappedBy: 'poll', cascade: ['remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->suggestions = new ArrayCollection();
        $this->votes = new ArrayCollection();
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

    public function getWinningSuggestion(): ?FilmSuggestion
    {
        return $this->winningSuggestion;
    }

    public function setWinningSuggestion(?FilmSuggestion $winningSuggestion): static
    {
        $this->winningSuggestion = $winningSuggestion;

        return $this;
    }

    public function getTiedSuggestions(): ?array
    {
        return $this->tiedSuggestions;
    }

    public function setTiedSuggestions(?array $tiedSuggestions): static
    {
        $this->tiedSuggestions = $tiedSuggestions;

        return $this;
    }

    /** @return Collection<int, FilmSuggestion> */
    public function getSuggestions(): Collection
    {
        return $this->suggestions;
    }

    /** @return Collection<int, FilmPollVote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }
}
