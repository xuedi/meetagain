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
        public ?int $parent = null,
    ) {}

    public function key(): string
    {
        return match (true) {
            $this->operation === ChangeOperation::Add && $this->parent !== null
                => sprintf('%s_add_%d_%s_%d', $this->axis->value, $this->parent, (string) $this->locale, (int) $this->index),
            $this->operation === ChangeOperation::Add
                => sprintf('%s_add_%s_%d', $this->axis->value, (string) $this->locale, (int) $this->index),
            $this->operation === ChangeOperation::Rename
                => sprintf('%s_rename_%d_%s', $this->axis->value, (int) $this->id, (string) $this->locale),
            default => sprintf('%s_remove_%d', $this->axis->value, (int) $this->id),
        };
    }

    public function labelKey(): string
    {
        if ($this->operation === ChangeOperation::Add && $this->parent !== null) {
            return sprintf('item.taxonomy_field_%s_add_child', $this->axis->value);
        }

        return sprintf('item.taxonomy_field_%s_%s', $this->axis->value, $this->operation->value);
    }
}
