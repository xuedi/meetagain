<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotFoundLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotFoundLogRepository::class)]
#[ORM\Table(name: 'logs_not_found')]
class NotFoundLog
{
    private const int URL_MAX = 2048;
    private const int UA_MAX = 512;
    private const int REFERER_MAX = 2048;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2048)]
    private ?string $url = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 45)]
    private ?string $ip = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $referer = null;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(name: 'incident_id', nullable: true, onDelete: 'SET NULL')]
    private ?Incident $incident = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = mb_substr($url, 0, self::URL_MAX);

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, self::UA_MAX);

        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): static
    {
        $this->referer = $referer === null ? null : mb_substr($referer, 0, self::REFERER_MAX);

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
