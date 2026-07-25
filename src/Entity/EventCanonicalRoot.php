<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\EventCanonicalRootType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(fields: ['event', 'locale'])]
#[ORM\Entity]
class EventCanonicalRoot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(length: 2)]
    private string $locale = '';

    #[ORM\Column(length: 10, enumType: EventCanonicalRootType::class)]
    private EventCanonicalRootType $type = EventCanonicalRootType::Root;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

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

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getType(): EventCanonicalRootType
    {
        return $this->type;
    }

    public function setType(EventCanonicalRootType $type): static
    {
        $this->type = $type;

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
}
