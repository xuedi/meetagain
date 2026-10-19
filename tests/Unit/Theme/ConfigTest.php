<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Theme\Config;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public static function rejectedValueProvider(): Generator
    {
        yield 'three digits' => ['#abc'];
        yield 'not hex' => ['#zzzzzz'];
        yield 'markup after the colour' => ['#123456;}</style><script>'];
        yield 'trailing newline' => ["#123456\n"];
        yield 'no hash' => ['123456'];
    }

    #[DataProvider('rejectedValueProvider')]
    public function testAMalformedColourIsDropped(string $value): void
    {
        // Act
        $config = Config::fromArray(['color_primary' => $value]);

        // Assert
        static::assertNull($config->get('color_primary'));
    }

    public function testAValidColourIsKeptInLowerCase(): void
    {
        // Act
        $config = Config::fromArray(['color_primary' => '#00D1B2']);

        // Assert
        static::assertSame('#00d1b2', $config->get('color_primary'));
    }
}
