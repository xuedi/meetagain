<?php declare(strict_types=1);

namespace App\Entity\ValueObjects;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Throwable;

// TODO: add strict validation and refactor more php 8'y
class LogEntry
{
    private readonly DateTimeImmutable $date;
    private readonly string $type;
    private readonly string $level;
    private string $message;
    private ?string $json = null;

    public function __construct(string $line)
    {
        $this->date = new DateTimeImmutable(substr($line, 1, strpos($line, ']') - 1));
        $line = substr($line, strpos($line, ']') + 2);

        $chunk = substr($line, 0, strpos($line, ':'));
        [$type, $level] = explode('.', $chunk);
        $this->type = $type;
        $this->level = $level;
        $line = substr($line, strpos($line, ':') + 2);

        if (!str_contains($line, '{')) {
            $this->message = $line;
        } else {
            $this->message = trim(substr($line, 0, strpos($line, '{')));
            $this->json = trim(substr($line, strpos($line, '{') - 1));
        }
    }

    public static function fromString(string $line): self
    {
        return new self($line);
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getJson(): ?string
    {
        return $this->json;
    }
}
