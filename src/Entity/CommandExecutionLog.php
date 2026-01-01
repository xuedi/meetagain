<?php declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_command_log_name', columns: ['command_name'])]
#[ORM\Index(name: 'idx_command_log_started', columns: ['started_at'])]
#[ORM\Index(name: 'idx_command_log_status', columns: ['status'])]
class CommandExecutionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $commandName = null;

    #[ORM\Column]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(length: 20, enumType: CommandExecutionStatus::class)]
    private CommandExecutionStatus $status = CommandExecutionStatus::Running;

    #[ORM\Column(nullable: true)]
    private ?int $exitCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorOutput = null;

    #[ORM\Column(length: 20, enumType: CommandTriggerType::class)]
    private CommandTriggerType $triggeredBy = CommandTriggerType::Cron;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommandName(): ?string
    {
        return $this->commandName;
    }

    public function setCommandName(string $commandName): static
    {
        $this->commandName = $commandName;

        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getStatus(): CommandExecutionStatus
    {
        return $this->status;
    }

    public function setStatus(CommandExecutionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function setExitCode(?int $exitCode): static
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): static
    {
        $this->output = $output;

        return $this;
    }

    public function getErrorOutput(): ?string
    {
        return $this->errorOutput;
    }

    public function setErrorOutput(?string $errorOutput): static
    {
        $this->errorOutput = $errorOutput;

        return $this;
    }

    public function getTriggeredBy(): CommandTriggerType
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(CommandTriggerType $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function getDuration(): ?int
    {
        if (!$this->startedAt instanceof DateTimeImmutable || !$this->completedAt instanceof DateTimeImmutable) {
            return null;
        }

        return $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }
}
