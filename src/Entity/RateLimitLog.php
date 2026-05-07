<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\RateLimitLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateLimitLogRepository::class)]
#[ORM\Table(name: 'logs_rate_limit')]
#[ORM\Index(name: 'idx_rate_limit_created_at', fields: ['createdAt'])]
#[ORM\Index(name: 'idx_rate_limit_ip_created_at', fields: ['ip', 'createdAt'])]
class RateLimitLog
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

    #[ORM\Column(length: 64)]
    private string $limiter;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userIdentifier = null;

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

    public function getLimiter(): string
    {
        return $this->limiter;
    }

    public function setLimiter(string $limiter): static
    {
        $this->limiter = mb_substr($limiter, 0, 64);

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): static
    {
        $this->userIdentifier = $userIdentifier === null
            ? null
            : mb_substr(strtolower($userIdentifier), 0, 255);

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
}
