<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\SupportRequestStatus;
use App\Repository\SupportRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportRequestRepository::class)]
class SupportRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 10, enumType: SupportRequestStatus::class)]
    private SupportRequestStatus $status = SupportRequestStatus::New;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

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

    public function getStatus(): SupportRequestStatus
    {
        return $this->status;
    }

    public function setStatus(SupportRequestStatus $status): static
    {
        $this->status = $status;

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

    public function isNew(): bool
    {
        return $this->status === SupportRequestStatus::New;
    }

    public function isRead(): bool
    {
        return $this->status === SupportRequestStatus::Read;
    }
}
