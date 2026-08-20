<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\SupportMessageAuthor;
use App\Repository\SupportMessageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportMessageRepository::class)]
#[ORM\Index(name: 'idx_support_message_thread', columns: ['support_request_id', 'created_at'])]
class SupportMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SupportRequest $supportRequest;

    #[ORM\Column(length: 10, enumType: SupportMessageAuthor::class)]
    private SupportMessageAuthor $author = SupportMessageAuthor::Requester;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $authorUser = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupportRequest(): SupportRequest
    {
        return $this->supportRequest;
    }

    public function setSupportRequest(SupportRequest $supportRequest): static
    {
        $this->supportRequest = $supportRequest;

        return $this;
    }

    public function getAuthor(): SupportMessageAuthor
    {
        return $this->author;
    }

    public function setAuthor(SupportMessageAuthor $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthorUser(): ?User
    {
        return $this->authorUser;
    }

    public function setAuthorUser(?User $authorUser): static
    {
        $this->authorUser = $authorUser;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function isFromAdmin(): bool
    {
        return $this->author === SupportMessageAuthor::Admin;
    }
}
