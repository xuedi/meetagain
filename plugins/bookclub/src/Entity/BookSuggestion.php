<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookSuggestionRepository;

#[ORM\Entity(repositoryClass: BookSuggestionRepository::class)]
class BookSuggestion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'suggestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\Column]
    private ?int $suggestedBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $suggestedAt = null;

    #[ORM\Column]
    private int $resubmitCount = 0;

    #[ORM\Column(enumType: SuggestionStatus::class)]
    private SuggestionStatus $status = SuggestionStatus::Pending;

    #[ORM\ManyToOne(inversedBy: 'suggestions')]
    private ?BookPoll $poll = null;

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

    public function getSuggestedBy(): ?int
    {
        return $this->suggestedBy;
    }

    public function setSuggestedBy(int $suggestedBy): static
    {
        $this->suggestedBy = $suggestedBy;

        return $this;
    }

    public function getSuggestedAt(): ?DateTimeImmutable
    {
        return $this->suggestedAt;
    }

    public function setSuggestedAt(DateTimeImmutable $suggestedAt): static
    {
        $this->suggestedAt = $suggestedAt;

        return $this;
    }

    public function getResubmitCount(): int
    {
        return $this->resubmitCount;
    }

    public function setResubmitCount(int $resubmitCount): static
    {
        $this->resubmitCount = $resubmitCount;

        return $this;
    }

    public function getStatus(): SuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(SuggestionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPoll(): ?BookPoll
    {
        return $this->poll;
    }

    public function setPoll(?BookPoll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }
}
