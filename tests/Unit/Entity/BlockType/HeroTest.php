<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\Hero;
use PHPUnit\Framework\TestCase;

class HeroTest extends TestCase
{
    public function testAScriptButtonLinkIsDropped(): void
    {
        // Arrange
        $json = [
            'headline' => 'Welcome',
            'subHeadline' => 'Play with us',
            'text' => 'Every Tuesday.',
            'buttonLink' => 'javascript:alert(document.cookie)',
            'buttonText' => 'Join',
        ];

        // Act
        $block = Hero::fromJson($json);

        // Assert
        static::assertSame('', $block->buttonLink);
        static::assertSame('', $block->toArray()['buttonLink']);
    }

    public function testARelativeButtonLinkIsKept(): void
    {
        // Arrange
        $json = ['headline' => 'Welcome', 'subHeadline' => '', 'text' => '', 'buttonLink' => '/register', 'buttonText' => 'Join'];

        // Act
        $block = Hero::fromJson($json);

        // Assert
        static::assertSame('/register', $block->buttonLink);
    }
}
