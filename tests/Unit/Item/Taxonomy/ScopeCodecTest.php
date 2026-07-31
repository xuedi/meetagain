<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\ScopeCodec;
use App\Publisher\PluginSettings\Resolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScopeCodecTest extends TestCase
{
    /** @return iterable<string, array{?string, ?int}> */
    public static function scopeProvider(): iterable
    {
        yield 'global scope is zero' => [null, 0];
        yield 'numeric scope keeps its value' => ['7', 7];
        yield 'a scope literally named zero collides with global' => ['0', null];
        yield 'a non-numeric scope cannot be encoded' => ['scope-a', null];
        yield 'a negative scope cannot be encoded' => ['-3', null];
    }

    #[DataProvider('scopeProvider')]
    public function testEncode(?string $scopeId, ?int $expected): void
    {
        // Arrange
        $codec = new ScopeCodec($this->createStub(Resolver::class));

        // Act + Assert
        static::assertSame($expected, $codec->encode($scopeId));
    }

    public function testDecodeIsTheInverseOfEncode(): void
    {
        // Arrange
        $codec = new ScopeCodec($this->createStub(Resolver::class));

        // Act + Assert
        static::assertNull($codec->decode(0));
        static::assertSame('7', $codec->decode(7));
    }

    public function testCurrentTargetIdFollowsTheResolvedScope(): void
    {
        // Arrange
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolveScopeId')->willReturn('12');

        // Act
        $codec = new ScopeCodec($resolver);

        // Assert
        static::assertSame(12, $codec->currentTargetId());
    }
}
