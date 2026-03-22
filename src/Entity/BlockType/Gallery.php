<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class Gallery implements BlockType
{
    private function __construct(
        public readonly string $title,
        public readonly array $images,
    ) {}

    #[Override]
    public static function getCapabilities(): BlockCapabilities
    {
        return new BlockCapabilities(image: ImageSupport::None, supportsImageRight: false, isGallery: true);
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return [
            new FieldDefinition('title', FieldType::String, required: false, default: ''),
            new FieldDefinition('images', FieldType::ImageList, required: false, default: []),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self(title: $json['title'] ?? '', images: $json['images'] ?? []);
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::Gallery;
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
