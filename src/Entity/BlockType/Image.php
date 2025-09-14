<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;

class Image implements BlockType
{
    private function __construct(
        public string $id,
        public null|ImageEntity $image,
    ) {}

    #[\Override]
    public static function fromJson(array $json, null|ImageEntity $image = null): self
    {
        return new self($json['id'], $image);
    }

    #[\Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Image;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'image' => $this->image,
        ];
    }
}
