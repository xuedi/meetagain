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
    private null|int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $title = null;

    #[ORM\Column]
    private null|int $createdBy = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private null|DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    private null|DateTimeImmutable $endDate = null;

    #[ORM\Column(enumType: PollStatus::class)]
    private PollStatus $status = PollStatus::Draft;

    #[ORM\Column(nullable: true)]
    private null|int $eventId = null;

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

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getTitle(): null|string
    {
        return $this->title;
    }

    public function setTitle(null|string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCreatedBy(): null|int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): null|DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartDate(): null|DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(null|DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): null|DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(null|DateTimeImmutable $endDate): static
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

    public function getEventId(): null|int
    {
        return $this->eventId;
    }

    public function setEventId(null|int $eventId): static
    {
        $this->eventId = $eventId;

        return $this;
    }
}
