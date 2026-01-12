<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

class DishSuggestion implements JsonSerializable
{
    public int $createdBy {
        get => $this->createdBy;
    }

    public DateTimeImmutable $createdAt {
        get => $this->createdAt;
    }

    public DishSuggestionField $field {
        get => $this->field;
    }

    public string $language {
        get => $this->language;
    }

    public string $value {
        get => $this->value;
    }

    public static function fromJson(array $data): self
    {
        if ($data === []) {
            throw new InvalidArgumentException('Invalid data');
        }

        return new self(
            createdBy: $data['createdBy'],
            createdAt: new DateTimeImmutable($data['createdAt']['date']),
            field: DishSuggestionField::from($data['field']),
            language: $data['language'],
            value: $data['value'],
        );
    }

    public static function create(
        int $createdBy,
        DishSuggestionField $field,
        string $language,
        string $value,
    ): self {
        return new self(
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            field: $field,
            language: $language,
            value: $value,
        );
    }

    private function __construct(
        int $createdBy,
        DateTimeImmutable $createdAt,
        DishSuggestionField $field,
        string $language,
        string $value,
    ) {
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->field = $field;
        $this->language = $language;
        $this->value = $value;
    }

    public function jsonSerialize(): array
    {
        return [
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'field' => $this->field->value,
            'language' => $this->language,
            'value' => $this->value,
        ];
    }

    public function getHash(): string
    {
        return sha1(json_encode($this->jsonSerialize()));
    }
}
