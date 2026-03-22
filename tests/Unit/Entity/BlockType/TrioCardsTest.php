<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\TrioCards;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\ImageSupport;
use PHPUnit\Framework\TestCase;

class TrioCardsTest extends TestCase
{
    // --- Arrange / Act / Assert ---

    public function testFromJsonWithFullData(): void
    {
        // Arrange
        $json = [
            'headline' => 'Need more reasons?',
            'cards' => [
                [
                    'image'       => ['id' => 42, 'hash' => 'abc123'],
                    'subHeadline' => 'Easy Integration',
                    'text'        => 'Drop-in replacement...',
                    'buttonText'  => 'Learn more',
                    'buttonLink'  => '/features',
                ],
                [
                    'image'       => null,
                    'subHeadline' => 'Lightning Fast',
                    'text'        => 'Average delivery time under 1 second...',
                    'buttonText'  => '',
                    'buttonLink'  => '',
                ],
                [
                    'image'       => ['id' => 43, 'hash' => 'def456'],
                    'subHeadline' => 'Great Support',
                    'text'        => 'Real humans respond within the hour...',
                    'buttonText'  => 'Contact us',
                    'buttonLink'  => '/contact',
                ],
            ],
        ];

        // Act
        $block = TrioCards::fromJson($json);

        // Assert
        static::assertSame('Need more reasons?', $block->headline);
        static::assertCount(3, $block->cards);
        static::assertSame(['id' => 42, 'hash' => 'abc123'], $block->cards[0]['image']);
        static::assertSame('Easy Integration', $block->cards[0]['subHeadline']);
        static::assertSame('Learn more', $block->cards[0]['buttonText']);
        static::assertNull($block->cards[1]['image']);
        static::assertSame('', $block->cards[1]['buttonText']);
        static::assertSame(['id' => 43, 'hash' => 'def456'], $block->cards[2]['image']);
    }

    public function testFromJsonWithEmptyDataProducesThreeCards(): void
    {
        // Arrange / Act
        $block = TrioCards::fromJson([]);

        // Assert
        static::assertSame('', $block->headline);
        static::assertCount(3, $block->cards);
        foreach ($block->cards as $card) {
            static::assertNull($card['image']);
            static::assertSame('', $card['subHeadline']);
            static::assertSame('', $card['text']);
            static::assertSame('', $card['buttonText']);
            static::assertSame('', $card['buttonLink']);
        }
    }

    public function testFromJsonWithPartialCardsArrayFillsMissingSlots(): void
    {
        // Arrange
        $json = [
            'cards' => [
                ['subHeadline' => 'Only one card', 'text' => 'Some text', 'buttonText' => '', 'buttonLink' => ''],
            ],
        ];

        // Act
        $block = TrioCards::fromJson($json);

        // Assert
        static::assertCount(3, $block->cards);
        static::assertSame('Only one card', $block->cards[0]['subHeadline']);
        static::assertSame('', $block->cards[1]['subHeadline']);
        static::assertSame('', $block->cards[2]['subHeadline']);
    }

    public function testToArrayRoundTrip(): void
    {
        // Arrange
        $json = [
            'headline' => 'Test',
            'cards' => [
                ['image' => ['id' => 1, 'hash' => 'hash1'], 'subHeadline' => 'A', 'text' => 'B', 'buttonText' => 'C', 'buttonLink' => '/c'],
                ['image' => null, 'subHeadline' => 'D', 'text' => 'E', 'buttonText' => '', 'buttonLink' => ''],
                ['image' => null, 'subHeadline' => 'F', 'text' => 'G', 'buttonText' => 'H', 'buttonLink' => '/h'],
            ],
        ];

        // Act
        $block = TrioCards::fromJson($json);
        $result = $block->toArray();

        // Assert
        static::assertSame('Test', $result['headline']);
        static::assertCount(3, $result['cards']);
        static::assertSame(['id' => 1, 'hash' => 'hash1'], $result['cards'][0]['image']);
        static::assertSame('A', $result['cards'][0]['subHeadline']);
        static::assertNull($result['cards'][1]['image']);
    }

    public function testGetTypeReturnsTrioCards(): void
    {
        // Arrange / Act / Assert
        static::assertSame(CmsBlockType::TrioCards, TrioCards::getType());
    }

    public function testGetCapabilitiesReturnsNoneImageSupport(): void
    {
        // Arrange / Act
        $caps = TrioCards::getCapabilities();

        // Assert
        static::assertSame(ImageSupport::None, $caps->image);
        static::assertFalse($caps->supportsImageRight);
        static::assertFalse($caps->isGallery);
    }
}
