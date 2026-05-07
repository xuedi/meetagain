<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\UrlProbingIncidentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UrlProbingIncidentRepository::class)]
#[ORM\Table(name: 'logs_url_probing_incident')]
#[ORM\Index(name: 'idx_url_probing_ip', fields: ['ip'])]
#[ORM\Index(name: 'idx_url_probing_started_at', fields: ['startedAt'])]
#[ORM\Index(name: 'idx_url_probing_ip_ended_at', fields: ['ip', 'endedAt'])]
class UrlProbingIncident
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

    #[ORM\Column(type: Types::INTEGER)]
    private int $probeCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $distinctUrlCount;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sampleUrls = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

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

    public function getProbeCount(): int
    {
        return $this->probeCount;
    }

    public function setProbeCount(int $probeCount): static
    {
        $this->probeCount = $probeCount;

        return $this;
    }

    public function getDistinctUrlCount(): int
    {
        return $this->distinctUrlCount;
    }

    public function setDistinctUrlCount(int $distinctUrlCount): static
    {
        $this->distinctUrlCount = $distinctUrlCount;

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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
