<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class ChangeField
{
    public function __construct(
        public Axis $axis,
        public ChangeOperation $operation,
        public ?int $id = null,
        public ?string $locale = null,
        public ?int $index = null,
    ) {}

    public function key(): string
    {
        return match ($this->operation) {
            ChangeOperation::Add => sprintf('%s_add_%s_%d', $this->axis->value, (string) $this->locale, (int) $this->index),
            ChangeOperation::Rename => sprintf('%s_rename_%d_%s', $this->axis->value, (int) $this->id, (string) $this->locale),
            ChangeOperation::Remove => sprintf('%s_remove_%d', $this->axis->value, (int) $this->id),
        };
    }

    public function labelKey(): string
    {
        return sprintf('item.taxonomy_field_%s_%s', $this->axis->value, $this->operation->value);
    }
}
