<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
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

    public SuggestionField $field {
        get => $this->field;
        set => $this->field = $value;
    }

    public string $value {
        get => $this->value;
        set => $this->value = $value;
    }

    public static function fromJson(array $data): self
    {
        if ($data === []) {
            throw new InvalidArgumentException('Invalid data');
        }

        return new self(
            createdBy: $data['createdBy'],
            createdAt: new DateTimeImmutable($data['createdAt']['date']),
            field: SuggestionField::from($data['field']),
            value: $data['value'],
        );
    }

    public static function fromParams(
        int $createdBy,
        DateTimeImmutable $createdAt,
        SuggestionField $field,
        string $value,
    ): self {
        return new self(
            createdBy: $createdBy,
            createdAt: $createdAt,
            field: $field,
            value: $value,
        );
    }

    private function __construct(int $createdBy, DateTimeImmutable $createdAt, SuggestionField $field, string $value)
    {
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->field = $field;
        $this->value = $value;
    }

    public function jsonSerialize(): array
    {
        return [
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'field' => $this->field->value,
            'value' => $this->value,
        ];
    }

    public function getHash(): string
    {
        return sha1(json_encode($this->jsonSerialize()));
    }
}
