<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\Image as ImageEntity;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use Override;

class FactsRow implements BlockType
{
    public const int MAX_FACTS = 6;

    /**
     * @param list<array{icon: string, label: string}> $facts
     */
    private function __construct(
        public readonly string $headline,
        public readonly array $facts,
    ) {}

    #[Override]
    public static function getCapabilities(): BlockCapabilities
    {
        return new BlockCapabilities(
            image: ImageSupport::None,
            supportsImageRight: false,
            isGallery: false,
        );
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return [
            new FieldDefinition('headline', FieldType::String, required: false, default: ''),
        ];
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        $rawFacts = is_array($json['facts'] ?? null) ? $json['facts'] : [];
        $facts = [];
        foreach ($rawFacts as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $icon = trim((string) ($raw['icon'] ?? ''));
            $label = trim((string) ($raw['label'] ?? ''));
            if ($icon === '' && $label === '') {
                continue;
            }
            $facts[] = ['icon' => $icon, 'label' => $label];
            if (count($facts) >= self::MAX_FACTS) {
                break;
            }
        }

        return new self(
            headline: trim((string) ($json['headline'] ?? '')),
            facts: $facts,
        );
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::FactsRow;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'facts' => $this->facts,
        ];
    }
}
