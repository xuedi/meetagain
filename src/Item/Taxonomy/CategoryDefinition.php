<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class CategoryDefinition extends AbstractDefinition
{
    /** @param array<string, string> $labels locale => label */
    public function __construct(
        int $id,
        array $labels,
        public ?int $group = null,
    ) {
        parent::__construct($id, $labels);
    }
}
