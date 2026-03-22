<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use App\Entity\Image as ImageEntity;
use Override;

class TrioCards implements BlockType
{
    public const int CARD_COUNT = 3;

    /**
     * @param list<array{
     *     image: array{id: int, hash: string}|null,
     *     subHeadline: string,
     *     text: string,
     *     buttonText: string,
     *     buttonLink: string,
     * }> $cards
     */
    private function __construct(
        public readonly string $headline,
        public readonly array $cards,
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
        $cards = [];
        $rawCards = $json['cards'] ?? [];

        for ($i = 0; $i < self::CARD_COUNT; $i++) {
            $card = $rawCards[$i] ?? [];
            $cardImage = isset($card['image']['id'], $card['image']['hash'])
                ? ['id' => (int) $card['image']['id'], 'hash' => (string) $card['image']['hash']]
                : null;

            $cards[] = [
                'image'       => $cardImage,
                'subHeadline' => (string) ($card['subHeadline'] ?? ''),
                'text'        => (string) ($card['text'] ?? ''),
                'buttonText'  => (string) ($card['buttonText'] ?? ''),
                'buttonLink'  => (string) ($card['buttonLink'] ?? ''),
            ];
        }

        return new self(
            headline: (string) ($json['headline'] ?? ''),
            cards: $cards,
        );
    }

    #[Override]
    public static function getType(): CmsBlockType
    {
        return CmsBlockType::TrioCards;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'cards' => $this->cards,
        ];
    }
}
