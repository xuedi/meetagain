<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class Hero implements BlockType
{
    private function __construct(
        public string $headline,
        public string $subHeadline,
        public string $text,
        public string $buttonLink,
        public string $buttonText,
        public string $color,
        public bool $imageRight,
        public ?ImageEntity $image,
    ) {}

    #[Override]
    public static function getCapabilities(): BlockCapabilities
    {
        return new BlockCapabilities(image: ImageSupport::Required, supportsImageRight: true, isGallery: false);
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return [
            new FieldDefinition('headline', FieldType::String),
            new FieldDefinition('subHeadline', FieldType::String),
            new FieldDefinition('text', FieldType::Text),
            new FieldDefinition('buttonLink', FieldType::String),
            new FieldDefinition('buttonText', FieldType::String),
            new FieldDefinition('color', FieldType::Color, required: false, default: '#f14668'),
            new FieldDefinition('imageRight', FieldType::Boolean, required: false, default: false),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self(
            $json['headline'],
            $json['subHeadline'],
            $json['text'],
            $json['buttonLink'],
            $json['buttonText'],
            $json['color'] ?? '#f14668',
            (bool) ($json['imageRight'] ?? false),
            $image,
        );
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::Hero;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'subHeadline' => $this->subHeadline,
            'text' => $this->text,
            'buttonLink' => $this->buttonLink,
            'buttonText' => $this->buttonText,
            'color' => $this->color,
            'imageRight' => $this->imageRight,
            'image' => $this->image,
        ];
    }
}
