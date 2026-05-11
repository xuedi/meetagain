<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccessDeniedLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessDeniedLogRepository::class)]
#[ORM\Table(name: 'logs_access_denied')]
#[ORM\Index(name: 'idx_access_denied_created_at', fields: ['createdAt'])]
#[ORM\Index(name: 'idx_access_denied_ip_created_at', fields: ['ip', 'createdAt'])]
class AccessDeniedLog
{
    private const int URL_MAX = 2048;
    private const int UA_MAX = 512;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 45)]
    private string $ip;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(name: 'incident_id', nullable: true, onDelete: 'SET NULL')]
    private ?Incident $incident = null;

    #[ORM\Column(length: 64)]
    private string $reason;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = mb_substr($url, 0, self::URL_MAX);

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = mb_substr($reason, 0, 64);

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, self::UA_MAX);

        return $this;
    }

    public function getIncident(): ?Incident
    {
        return $this->incident;
    }

    public function setIncident(?Incident $incident): static
    {
        $this->incident = $incident;

        return $this;
    }
}
