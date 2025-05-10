<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;

interface BlockType
{
    public static function fromJson(array $json): self;
    public static function getType(): CmsBlockTypes;
    public function toArray(): array;
}
