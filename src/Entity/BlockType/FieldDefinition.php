<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\FieldType;

readonly class FieldDefinition
{
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $required = true,
        public string|bool|array|null $default = null,
    ) {}
}
