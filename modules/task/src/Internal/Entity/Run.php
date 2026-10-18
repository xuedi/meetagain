<?php declare(strict_types=1);

namespace Module\Task\Internal\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Task\Contract\Status;
use Module\Task\Internal\Repository\RunRepository;

#[ORM\Entity(repositoryClass: RunRepository::class)]
#[ORM\Table(name: 'mod_task_run')]
#[ORM\Index(name: 'idx_task_run_identifier_started', columns: ['identifier', 'started_at'])]
#[ORM\Index(name: 'idx_task_run_started', columns: ['started_at'])]
class Run
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $identifier,
        #[ORM\Column]
        private DateTimeImmutable $startedAt,
        #[ORM\Column]
        private int $durationMs,
        #[ORM\Column(length: 16, enumType: Status::class)]
        private Status $status,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(length: 120)]
        private string $host,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
