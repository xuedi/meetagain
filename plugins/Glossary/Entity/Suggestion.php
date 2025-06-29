<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use DateTimeImmutable;
use JsonSerializable;

class Suggestion implements JsonSerializable
{
    public int $createdBy {
        get => $this->createdBy;
        set => $this->createdBy = $value;
    }

    public DateTimeImmutable $createdAt {
        get => $this->createdAt;
        set => $this->createdAt = $value;
    }

    public string $field {
        get => $this->field;
        set => $this->field = $value;
    }

    public string $value {
        get => $this->value;
        set => $this->value = $value;
    }

    public static function fromJson(?array $data): self
    {
        return new self($data ?? []);
    }

    public function __construct(array $data)
    {
        $this->createdBy = $data['createdBy'];
        $this->createdAt = new DateTimeImmutable($data['createdAt']['date']);
        $this->field = $data['field'];
        $this->value = $data['value'];
    }

    public function jsonSerialize(): array
    {
        return [
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'field' => $this->field,
            'value' => $this->value,
        ];
    }
}
