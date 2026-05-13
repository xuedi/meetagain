<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmSelectionRepository;

#[ORM\Entity(repositoryClass: FilmSelectionRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_event_film_selection', columns: ['event_id', 'film_id'])]
class FilmSelection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Film $film = null;

    #[ORM\Column]
    private ?int $eventId = null;

    #[ORM\Column]
    private ?int $selectedBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $selectedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(Film $film): static
    {
        $this->film = $film;

        return $this;
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

    public function getSelectedBy(): ?int
    {
        return $this->selectedBy;
    }

    public function setSelectedBy(int $selectedBy): static
    {
        $this->selectedBy = $selectedBy;

        return $this;
    }

    public function getSelectedAt(): ?DateTimeImmutable
    {
        return $this->selectedAt;
    }

    public function setSelectedAt(DateTimeImmutable $selectedAt): static
    {
        $this->selectedAt = $selectedAt;

        return $this;
    }
}
