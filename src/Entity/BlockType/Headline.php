<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class Headline implements BlockType
{
    private function __construct(
        public string $title,
    ) {}

    #[Override]
    public static function getCapabilities(): BlockCapabilities
    {
        return new BlockCapabilities(image: ImageSupport::None, supportsImageRight: false, isGallery: false);
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return [
            new FieldDefinition('title', FieldType::String),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self($json['title']);
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::Headline;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
