<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class EventTeaser implements BlockType
{
    private function __construct(
        public string $headline,
        public string $text,
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
            new FieldDefinition('headline', FieldType::String),
            new FieldDefinition('text', FieldType::Text),
            new FieldDefinition('imageRight', FieldType::Boolean, required: false, default: false),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self($json['headline'], $json['text'], (bool) ($json['imageRight'] ?? false), $image);
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::EventTeaser;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'text' => $this->text,
            'imageRight' => $this->imageRight,
            'image' => $this->image,
        ];
    }
}
