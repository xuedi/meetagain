<?php declare(strict_types=1);

namespace Plugin\Voting\Entity;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Voting\Repository\PollRepository;

#[ORM\Entity(repositoryClass: PollRepository::class)]
class Poll
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(length: 50)]
    private ?string $itemType = null;

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

    #[ORM\Column(nullable: true)]
    private ?int $winningItemId = null;

    /** @var list<int>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tiedItemIds = null;

    /** @var Collection<int, PollOption> */
    #[ORM\OneToMany(targetEntity: PollOption::class, mappedBy: 'poll', cascade: ['persist', 'remove'])]
    private Collection $options;

    public function __construct()
    {
        $this->options = new ArrayCollection();
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

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

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

    public function getWinningItemId(): ?int
    {
        return $this->winningItemId;
    }

    public function setWinningItemId(?int $winningItemId): static
    {
        $this->winningItemId = $winningItemId;

        return $this;
    }

    /** @return list<int>|null */
    public function getTiedItemIds(): ?array
    {
        return $this->tiedItemIds;
    }

    /** @param list<int>|null $tiedItemIds */
    public function setTiedItemIds(?array $tiedItemIds): static
    {
        $this->tiedItemIds = $tiedItemIds;

        return $this;
    }

    /** @return Collection<int, PollOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(PollOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setPoll($this);
        }

        return $this;
    }

    public function removeOption(PollOption $option): static
    {
        $this->options->removeElement($option);

        return $this;
    }

    /** @return list<int> the candidate item ids on the ballot */
    public function getOptionItemIds(): array
    {
        return array_values(array_map(static fn(PollOption $o): int => (int) $o->getItemId(), $this->options->toArray()));
    }
}
