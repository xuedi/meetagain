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
class Incident
{
    private const int UA_MAX = 512;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private string $ip;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endedAt;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $probingHits = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $accessDeniedHits = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $rateLimitHits = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalHits = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $distinctPaths = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $distinctUserAgents = 0;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sampleUrls = [];

    #[ORM\Column(type: Types::STRING, length: 16, enumType: IncidentSeverity::class, options: ['default' => 'low'])]
    private IncidentSeverity $severity = IncidentSeverity::Low;

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

    public function getProbingHits(): int
    {
        return $this->probingHits;
    }

    public function setProbingHits(int $probingHits): static
    {
        $this->probingHits = $probingHits;

        return $this;
    }

    public function getAccessDeniedHits(): int
    {
        return $this->accessDeniedHits;
    }

    public function setAccessDeniedHits(int $accessDeniedHits): static
    {
        $this->accessDeniedHits = $accessDeniedHits;

        return $this;
    }

    public function getRateLimitHits(): int
    {
        return $this->rateLimitHits;
    }

    public function setRateLimitHits(int $rateLimitHits): static
    {
        $this->rateLimitHits = $rateLimitHits;

        return $this;
    }

    public function getTotalHits(): int
    {
        return $this->totalHits;
    }

    public function setTotalHits(int $totalHits): static
    {
        $this->totalHits = $totalHits;

        return $this;
    }

    public function getDistinctPaths(): int
    {
        return $this->distinctPaths;
    }

    public function setDistinctPaths(int $distinctPaths): static
    {
        $this->distinctPaths = $distinctPaths;

        return $this;
    }

    public function getDistinctUserAgents(): int
    {
        return $this->distinctUserAgents;
    }

    public function setDistinctUserAgents(int $distinctUserAgents): static
    {
        $this->distinctUserAgents = $distinctUserAgents;

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

    /**
     * @return list<string>
     */
    public function getSampleUrls(): array
    {
        return $this->sampleUrls;
    }

    /**
     * @param list<string> $sampleUrls
     */
    public function setSampleUrls(array $sampleUrls): static
    {
        $this->sampleUrls = array_values($sampleUrls);

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
