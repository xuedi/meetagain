<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookPollRepository;

#[ORM\Entity(repositoryClass: BookPollRepository::class)]
class BookPoll
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $endDate = null;

    #[ORM\Column(enumType: PollStatus::class)]
    private PollStatus $status = PollStatus::Active;

    #[ORM\Column]
    private int $eventId;

    /** @var Collection<int, BookSuggestion> */
    #[ORM\OneToMany(targetEntity: BookSuggestion::class, mappedBy: 'poll')]
    private Collection $suggestions;

    /** @var Collection<int, BookPollVote> */
    #[ORM\OneToMany(targetEntity: BookPollVote::class, mappedBy: 'poll')]
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

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

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

    /** @return Collection<int, BookSuggestion> */
    public function getSuggestions(): Collection
    {
        return $this->suggestions;
    }

    public function addSuggestion(BookSuggestion $suggestion): static
    {
        if (!$this->suggestions->contains($suggestion)) {
            $this->suggestions->add($suggestion);
            $suggestion->setPoll($this);
        }

        return $this;
    }

    /** @return Collection<int, BookPollVote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): static
    {
        $this->eventId = $eventId;

        return $this;
    }
}
