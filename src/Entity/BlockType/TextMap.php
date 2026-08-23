<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\Image as ImageEntity;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Enum\CmsBlock\MapHeight;
use App\Enum\CmsBlock\MapPosition;
use App\Enum\CmsBlock\MapWidth;
use Override;

class TextMap implements BlockType
{
    public const int DEFAULT_ZOOM = 15;
    public const int MIN_ZOOM = 1;
    public const int MAX_ZOOM = 19;

    private function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly int $zoom,
        public readonly string $markerLabel,
        public readonly MapPosition $mapPosition,
        public readonly MapWidth $mapWidth,
        public readonly MapHeight $mapHeight,
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
            new FieldDefinition('title', FieldType::String, required: false, default: ''),
            new FieldDefinition('content', FieldType::Text, richText: true),
            new FieldDefinition('latitude', FieldType::String, required: false, default: ''),
            new FieldDefinition('longitude', FieldType::String, required: false, default: ''),
            new FieldDefinition('zoom', FieldType::String, required: false, default: (string) self::DEFAULT_ZOOM),
            new FieldDefinition('markerLabel', FieldType::String, required: false, default: ''),
            new FieldDefinition('mapPosition', FieldType::String, required: false, default: MapPosition::Right->value),
            new FieldDefinition('mapWidth', FieldType::String, required: false, default: MapWidth::Third->value),
            new FieldDefinition('mapHeight', FieldType::String, required: false, default: MapHeight::Medium->value),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self(
            title: trim((string) ($json['title'] ?? '')),
            content: (string) ($json['content'] ?? ''),
            latitude: self::toCoordinate($json['latitude'] ?? null, 90.0),
            longitude: self::toCoordinate($json['longitude'] ?? null, 180.0),
            zoom: self::toZoom($json['zoom'] ?? null),
            markerLabel: trim((string) ($json['markerLabel'] ?? '')),
            mapPosition: MapPosition::tryFrom((string) ($json['mapPosition'] ?? '')) ?? MapPosition::Right,
            mapWidth: MapWidth::tryFrom((string) ($json['mapWidth'] ?? '')) ?? MapWidth::Third,
            mapHeight: MapHeight::tryFrom((string) ($json['mapHeight'] ?? '')) ?? MapHeight::Medium,
        );
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::TextMap;
    }

    public function hasMap(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zoom' => $this->zoom,
            'markerLabel' => $this->markerLabel,
            'mapPosition' => $this->mapPosition->value,
            'mapWidth' => $this->mapWidth->value,
            'mapHeight' => $this->mapHeight->value,
        ];
    }

    private static function toCoordinate(mixed $raw, float $limit): ?float
    {
        if (!is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return abs($value) > $limit ? null : $value;
    }

    private static function toZoom(mixed $raw): int
    {
        if (!is_numeric($raw)) {
            return self::DEFAULT_ZOOM;
        }

        return max(self::MIN_ZOOM, min(self::MAX_ZOOM, (int) $raw));
    }
}
