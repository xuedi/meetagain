<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Bookclub\Repository\BookNoteRepository;

#[ORM\Entity(repositoryClass: BookNoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_book', columns: ['user_id', 'book_id'])]
class BookNote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private null|Book $book = null;

    #[ORM\Column]
    private null|int $userId = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $content = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private null|DateTimeImmutable $updatedAt = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getBook(): null|Book
    {
        return $this->book;
    }

    public function setBook(Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getUserId(): null|int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getContent(): null|string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    public function getUpdatedAt(): null|DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(null|DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
