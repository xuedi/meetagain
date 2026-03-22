<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class Text implements BlockType
{
    private function __construct(
        public string $title,
        public string $content,
        public bool $imageRight,
        public ?ImageEntity $image,
    ) {}

    #[Override]
    public static function getCapabilities(): BlockCapabilities
    {
        return new BlockCapabilities(image: ImageSupport::Optional, supportsImageRight: true, isGallery: false);
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return [
            new FieldDefinition('title', FieldType::String, required: false, default: ''),
            new FieldDefinition('content', FieldType::Text),
            new FieldDefinition('imageRight', FieldType::Boolean, required: false, default: false),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self($json['title'] ?? '', $json['content'], (bool) ($json['imageRight'] ?? false), $image);
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::Text;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'imageRight' => $this->imageRight,
            'image' => $this->image,
        ];
    }
}
