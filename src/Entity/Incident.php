<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\IncidentSeverity;
use App\Repository\IncidentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'logs_incident')]
#[ORM\Index(name: 'idx_incident_ip', fields: ['ip'])]
#[ORM\Index(name: 'idx_incident_started_at', fields: ['startedAt'])]
#[ORM\Index(name: 'idx_incident_ended_at', fields: ['endedAt'])]
#[ORM\Index(name: 'idx_incident_ip_ended_at', fields: ['ip', 'endedAt'])]
#[ORM\Index(name: 'idx_incident_severity', fields: ['severity'])]
#[ORM\Index(name: 'idx_incident_session_id', fields: ['sessionId'])]
#[ORM\Index(name: 'idx_incident_triggered_by', fields: ['triggeredBy'])]
class Incident
{
    private const int UA_MAX = 512;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private string $ip;

    #[ORM\Column(length: 128)]
    private string $sessionId = '';

    #[ORM\Column(length: 32)]
    private string $triggeredBy = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endedAt;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: IncidentSeverity::class, options: ['default' => 'low'])]
    private IncidentSeverity $severity = IncidentSeverity::Low;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $providerReports = [];

    #[ORM\Column(length: 64)]
    private string $blockedUntilDescription = '';

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = mb_substr($sessionId, 0, 128);

        return $this;
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(string $triggeredBy): static
    {
        $this->triggeredBy = mb_substr($triggeredBy, 0, 32);

        return $this;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

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

    public function getSeverity(): IncidentSeverity
    {
        return $this->severity;
    }

    public function setSeverity(IncidentSeverity $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProviderReports(): array
    {
        return $this->providerReports;
    }

    /**
     * @param list<array<string, mixed>> $providerReports
     */
    public function setProviderReports(array $providerReports): static
    {
        $this->providerReports = array_values($providerReports);

        return $this;
    }

    public function getBlockedUntilDescription(): string
    {
        return $this->blockedUntilDescription;
    }

    public function setBlockedUntilDescription(string $blockedUntilDescription): static
    {
        $this->blockedUntilDescription = mb_substr($blockedUntilDescription, 0, 64);

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;

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

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
