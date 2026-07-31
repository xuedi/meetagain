<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class TagDefinition extends AbstractDefinition
{
    /** @param array<string, string> $labels locale => label */
    public function __construct(
        int $id,
        array $labels,
        public ?int $parent = null,
    ) {
        parent::__construct($id, $labels);
    }
}
