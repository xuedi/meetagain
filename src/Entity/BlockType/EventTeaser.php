<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class EventTeaser implements BlockType
{
    private const int DEFAULT_EVENT_COUNT = 4;
    private const int MIN_EVENT_COUNT = 1;
    private const int MAX_EVENT_COUNT = 20;

    private function __construct(
        public string $headline,
        public string $text,
        public int $eventCount,
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
            new FieldDefinition('eventCount', FieldType::String, required: false, default: (string) self::DEFAULT_EVENT_COUNT),
            new FieldDefinition('imageRight', FieldType::Boolean, required: false, default: false),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        $eventCount = (int) ($json['eventCount'] ?? self::DEFAULT_EVENT_COUNT);
        $eventCount = max(self::MIN_EVENT_COUNT, min(self::MAX_EVENT_COUNT, $eventCount));

        return new self(
            $json['headline'],
            $json['text'],
            $eventCount,
            (bool) ($json['imageRight'] ?? false),
            $image,
        );
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
            'eventCount' => $this->eventCount,
            'imageRight' => $this->imageRight,
            'image' => $this->image,
        ];
    }
}
