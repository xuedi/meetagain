<?php declare(strict_types=1);

namespace Module\Ballot\Internal\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use Module\Ballot\Internal\Repository\BallotRepository;

#[ORM\Entity(repositoryClass: BallotRepository::class)]
#[ORM\Table(name: 'mod_ballot')]
#[ORM\Index(name: 'idx_ballot_purpose', columns: ['purpose'])]
#[ORM\Index(name: 'idx_ballot_status_deadline', columns: ['status', 'deadline'])]
#[ORM\Index(name: 'idx_ballot_subject', columns: ['subject_type', 'subject_id'])]
class Ballot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private string $purpose;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectType = null;

    #[ORM\Column(nullable: true)]
    private ?int $subjectId = null;

    #[ORM\Column(length: 16, enumType: BallotStatus::class)]
    private BallotStatus $status = BallotStatus::Open;

    #[ORM\Column(length: 16, enumType: TallyMode::class)]
    private TallyMode $tallyMode;

    #[ORM\Column(length: 16, enumType: SettlementMode::class)]
    private SettlementMode $settlementMode;

    #[ORM\Column]
    private DateTimeImmutable $deadline;

    #[ORM\Column]
    private int $openedByUserId;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $winningKey = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $tiedKeys = [];

    #[ORM\Column(nullable: true)]
    private ?int $settledByUserId = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $settledAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, BallotOption> */
    #[ORM\OneToMany(targetEntity: BallotOption::class, mappedBy: 'ballot', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    public function __construct(
        string $purpose,
        DateTimeImmutable $deadline,
        int $openedByUserId,
        TallyMode $tallyMode,
        SettlementMode $settlementMode,
        DateTimeImmutable $now,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $title = null,
    ) {
        $this->purpose = $purpose;
        $this->deadline = $deadline;
        $this->openedByUserId = $openedByUserId;
        $this->tallyMode = $tallyMode;
        $this->settlementMode = $settlementMode;
        $this->createdAt = $now;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->title = $title;
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    public function getStatus(): BallotStatus
    {
        return $this->status;
    }

    public function getTallyMode(): TallyMode
    {
        return $this->tallyMode;
    }

    public function getSettlementMode(): SettlementMode
    {
        return $this->settlementMode;
    }

    public function getDeadline(): DateTimeImmutable
    {
        return $this->deadline;
    }

    public function getOpenedByUserId(): int
    {
        return $this->openedByUserId;
    }

    public function getWinningKey(): ?string
    {
        return $this->winningKey;
    }

    /** @return list<string> */
    public function getTiedKeys(): array
    {
        return $this->tiedKeys;
    }

    public function getSettledByUserId(): ?int
    {
        return $this->settledByUserId;
    }

    public function getSettledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, BallotOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(BallotOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
        }

        return $this;
    }

    /** @return list<string> */
    public function getOptionKeys(): array
    {
        $keys = [];
        foreach ($this->options as $option) {
            $keys[] = $option->getOptionKey();
        }

        return $keys;
    }

    /** @param list<string> $tiedKeys */
    public function recordTally(?string $winningKey, array $tiedKeys): static
    {
        $this->status = BallotStatus::Tallied;
        $this->winningKey = $winningKey;
        $this->tiedKeys = $tiedKeys;

        return $this;
    }

    public function recordSettlement(string $winningKey, ?int $settledByUserId, DateTimeImmutable $now): static
    {
        $this->status = BallotStatus::Settled;
        $this->winningKey = $winningKey;
        $this->tiedKeys = [];
        $this->settledByUserId = $settledByUserId;
        $this->settledAt = $now;

        return $this;
    }

    public function abandon(?int $abandonedByUserId, DateTimeImmutable $now): static
    {
        $this->status = BallotStatus::Abandoned;
        $this->settledByUserId = $abandonedByUserId;
        $this->settledAt = $now;

        return $this;
    }

    /** @param list<string> $tiedKeys */
    public function restoreOutcome(BallotStatus $status, ?string $winningKey, array $tiedKeys, ?int $settledByUserId, ?DateTimeImmutable $settledAt): static
    {
        $this->status = $status;
        $this->winningKey = $winningKey;
        $this->tiedKeys = $tiedKeys;
        $this->settledByUserId = $settledByUserId;
        $this->settledAt = $settledAt;

        return $this;
    }
}
