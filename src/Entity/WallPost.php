<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\WallPostRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WallPostRepository::class)]
#[ORM\Table(name: 'wall_post')]
class WallPost
{
    public const int MAX_CONTENT_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    /**
     * @var Collection<int, WallReply>
     */
    #[ORM\OneToMany(targetEntity: WallReply::class, mappedBy: 'post', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function setEditedAt(?DateTimeImmutable $editedAt): static
    {
        $this->editedAt = $editedAt;

        return $this;
    }

    /**
     * @return Collection<int, WallReply>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }
}
