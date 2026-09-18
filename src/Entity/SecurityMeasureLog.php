<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\SecurityMeasure;
use App\Enum\SecurityMeasureOutcome;
use App\Repository\SecurityMeasureLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityMeasureLogRepository::class)]
#[ORM\Table(name: 'logs_security_measure')]
#[ORM\Index(name: 'idx_security_measure_day', fields: ['day', 'measure'])]
#[ORM\Index(name: 'idx_security_measure_created_at', fields: ['createdAt'])]
#[ORM\Index(name: 'idx_security_measure_outcome', fields: ['measure', 'outcome', 'day'])]
class SecurityMeasureLog
{
    private const int CONTEXT_MAX = 64;
    private const int UA_MAX = 512;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $day;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 32, enumType: SecurityMeasure::class)]
    private SecurityMeasure $measure;

    #[ORM\Column(length: 16, enumType: SecurityMeasureOutcome::class)]
    private SecurityMeasureOutcome $outcome;

    #[ORM\Column]
    private int $count = 1;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    /** @var array<string, scalar|null>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $detail = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(DateTimeImmutable $day): static
    {
        $this->day = $day;

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

    public function getMeasure(): SecurityMeasure
    {
        return $this->measure;
    }

    public function setMeasure(SecurityMeasure $measure): static
    {
        $this->measure = $measure;

        return $this;
    }

    public function getOutcome(): SecurityMeasureOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(SecurityMeasureOutcome $outcome): static
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context === null ? null : mb_substr($context, 0, self::CONTEXT_MAX);

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
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

    /**
     * @return array<string, scalar|null>|null
     */
    public function getDetail(): ?array
    {
        return $this->detail;
    }

    /**
     * @param array<string, scalar|null>|null $detail
     */
    public function setDetail(?array $detail): static
    {
        $this->detail = $detail;

        return $this;
    }
}
