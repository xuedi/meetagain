<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailBlocklistRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailBlocklistRepository::class)]
#[ORM\Table(name: 'email_blocklist')]
#[ORM\UniqueConstraint(name: 'uniq_email_blocklist_email', columns: ['email'])]
class EmailBlocklistEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'added_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $addedBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $addedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function getAddedAt(): ?DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function setAddedAt(DateTimeImmutable $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }
}
