<?php declare(strict_types=1);

namespace App\Review;

use App\Enum\FieldResolution;

final readonly class FieldChange
{
    public function __construct(
        public string $field,
        public ?string $before,
        public ?string $after,
        public ?FieldResolution $resolution = null,
    ) {}

    /**
     * @param array{before?: ?string, after?: ?string, resolution?: ?string} $data
     */
    public static function fromArray(string $field, array $data): self
    {
        $resolution = $data['resolution'] ?? null;

        return new self(
            field: $field,
            before: $data['before'] ?? null,
            after: $data['after'] ?? null,
            resolution: $resolution === null ? null : FieldResolution::from($resolution),
        );
    }

    /**
     * @return array{before: ?string, after: ?string, resolution: ?string}
     */
    public function toArray(): array
    {
        return [
            'before' => $this->before,
            'after' => $this->after,
            'resolution' => $this->resolution?->value,
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolution !== null;
    }

    public function isApplied(): bool
    {
        return $this->resolution === FieldResolution::Applied;
    }
}
