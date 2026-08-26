<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CirculationLedgerEntryRepository::class)]
#[ORM\Table(name: 'circulation_ledger')]
#[ORM\Index(name: 'idx_circulation_ledger_context', columns: ['context', 'id'])]
#[ORM\Index(name: 'idx_circulation_ledger_item', columns: ['context', 'item_type', 'item_id'])]
#[ORM\Index(name: 'idx_circulation_ledger_copy', columns: ['copy_id'])]
#[ORM\Index(name: 'idx_circulation_ledger_type', columns: ['context', 'entry_type'])]
class CirculationLedgerEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private DateTimeImmutable $recordedAt;

    #[ORM\Column(length: 30, enumType: CirculationLedgerEntryType::class)]
    private CirculationLedgerEntryType $entryType;

    #[ORM\Column(length: 191)]
    private string $context;

    #[ORM\Column(length: 50)]
    private string $itemType;

    #[ORM\Column]
    private int $itemId;

    #[ORM\Column(nullable: true)]
    private ?int $copyId = null;

    #[ORM\Column(nullable: true)]
    private ?int $fromUserId = null;

    #[ORM\Column(nullable: true)]
    private ?int $toUserId = null;

    #[ORM\Column(nullable: true)]
    private ?int $actorUserId = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        CirculationLedgerEntryType $entryType,
        string $context,
        string $itemType,
        int $itemId,
        DateTimeImmutable $occurredAt,
        ?int $copyId = null,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $actorUserId = null,
        array $payload = [],
    ) {
        $this->entryType = $entryType;
        $this->context = $context;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->occurredAt = $occurredAt;
        $this->copyId = $copyId;
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->actorUserId = $actorUserId;
        $this->payload = $payload;
        $this->recordedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getEntryType(): CirculationLedgerEntryType
    {
        return $this->entryType;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getCopyId(): ?int
    {
        return $this->copyId;
    }

    public function getFromUserId(): ?int
    {
        return $this->fromUserId;
    }

    public function getToUserId(): ?int
    {
        return $this->toUserId;
    }

    public function getActorUserId(): ?int
    {
        return $this->actorUserId;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
