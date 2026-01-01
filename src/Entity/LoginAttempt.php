<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Index(columns: ['attempted_at'], name: 'idx_login_attempt_time')]
#[ORM\Index(columns: ['ip'], name: 'idx_login_attempt_ip')]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?DateTimeImmutable $attemptedAt = null;

    #[ORM\Column]
    private bool $successful = false;

    #[ORM\Column(length: 45)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAttemptedAt(): ?DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(DateTimeImmutable $attemptedAt): static
    {
        $this->attemptedAt = $attemptedAt;

        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): static
    {
        $this->successful = $successful;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }
}
