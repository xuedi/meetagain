<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\CronTaskStatus;
use App\Repository\CronLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CronLogRepository::class)]
#[ORM\Index(fields: ['runAt'])]
class CronLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $runAt;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CronTaskStatus::class)]
    private CronTaskStatus $status;

    #[ORM\Column(type: Types::INTEGER)]
    private int $durationMs;

    /**
     * @var array<int, array{identifier: string, status: string, message: string, duration_ms: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $tasks;

    /**
     * @param array<int, array{identifier: string, status: string, message: string, duration_ms: int}> $tasks
     */
    public function __construct(DateTimeImmutable $runAt, CronTaskStatus $status, int $durationMs, array $tasks)
    {
        $this->runAt = $runAt;
        $this->status = $status;
        $this->durationMs = $durationMs;
        $this->tasks = $tasks;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRunAt(): DateTimeImmutable
    {
        return $this->runAt;
    }

    public function getStatus(): CronTaskStatus
    {
        return $this->status;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return array<int, array{identifier: string, status: string, message: string, duration_ms: int}>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}
