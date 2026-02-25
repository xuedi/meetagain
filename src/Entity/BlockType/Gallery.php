<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;
use Override;

class Gallery implements BlockType
{
    private function __construct(
        public readonly string $title,
        public readonly array $images,
    ) {}

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self(title: $json['title'] ?? '', images: $json['images'] ?? []);
    }

    #[Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Gallery;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'images' => $this->images,
        ];
    }
}
