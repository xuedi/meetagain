<?php declare(strict_types=1);

namespace Plugin\Fediverse\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Fediverse\Repository\PublicationRepository;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'plg_fediverse_publication')]
#[ORM\Index(name: 'idx_fediverse_publication_event', columns: ['event_id'])]
#[ORM\Index(name: 'idx_fediverse_publication_start', columns: ['event_starts_at'])]
class Publication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column]
    private int $eventId;

    #[ORM\Column]
    private DateTimeImmutable $eventStartsAt;

    #[ORM\Column(type: Types::TEXT)]
    private string $document = '';

    #[ORM\Column(length: 64)]
    private string $hash = '';

    #[ORM\Column]
    private bool $announced = false;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(Account $account, int $eventId, DateTimeImmutable $eventStartsAt, DateTimeImmutable $createdAt)
    {
        $this->account = $account;
        $this->eventId = $eventId;
        $this->eventStartsAt = $eventStartsAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    /** @return array<string, mixed> */
    public function getDocument(): array
    {
        $document = json_decode($this->document, true);

        return is_array($document) ? $document : [];
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getEventStartsAt(): DateTimeImmutable
    {
        return $this->eventStartsAt;
    }

    /** @param array<string, mixed> $document */
    public function publish(array $document, string $hash, DateTimeImmutable $eventStartsAt, DateTimeImmutable $at): static
    {
        $this->eventStartsAt = $eventStartsAt;
        $this->document = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->hash = $hash;
        $this->updatedAt = $at;

        return $this;
    }

    public function isAnnounced(): bool
    {
        return $this->announced;
    }

    public function markAnnounced(): static
    {
        $this->announced = true;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function delete(DateTimeImmutable $at): static
    {
        $this->deletedAt ??= $at;

        return $this;
    }
}
