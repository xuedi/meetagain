<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailTemplateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private null|string $identifier = null;

    #[ORM\Column(length: 255)]
    private null|string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $body = null;

    #[ORM\Column(type: Types::JSON)]
    private array $availableVariables = [];

    #[ORM\Column]
    private null|DateTimeImmutable $updatedAt = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getIdentifier(): null|string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getSubject(): null|string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): null|string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    public function setAvailableVariables(array $availableVariables): static
    {
        $this->availableVariables = $availableVariables;

        return $this;
    }

    public function getUpdatedAt(): null|DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
