<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlockType;
use App\Entity\Image as ImageEntity;

interface BlockType
{
    public static function fromJson(array $json, ?ImageEntity $image = null): self;

    public static function getType(): CmsBlockType;

    public function toArray(): array;
}
