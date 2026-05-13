<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Filmclub\Repository\FilmWishlistEntryRepository;

#[ORM\Entity(repositoryClass: FilmWishlistEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_film_wishlist', columns: ['user_id', 'film_id'])]
class FilmWishlistEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'wishlistEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Film $film = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\Column]
    private int $priorityCounter = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getPriorityCounter(): int
    {
        return $this->priorityCounter;
    }

    public function setPriorityCounter(int $priorityCounter): static
    {
        $this->priorityCounter = $priorityCounter;

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
