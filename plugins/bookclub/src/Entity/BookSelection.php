<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookSelectionRepository;

#[ORM\Entity(repositoryClass: BookSelectionRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_event_book', columns: ['event_id', 'book_id'])]
class BookSelection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'selections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column]
    private ?int $selectedBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $selectedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(Book $book): static
    {
        $this->book = $book;

        return $this;
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
