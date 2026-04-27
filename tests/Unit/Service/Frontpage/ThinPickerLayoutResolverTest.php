<?php declare(strict_types=1);

namespace Tests\Unit\Service\Frontpage;

use App\Enum\LandingLayout;
use App\Service\Frontpage\ThinPickerLayoutResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThinPickerLayoutResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{int, LandingLayout}>
     */
    public static function counts(): iterable
    {
        yield 'zero languages -> single' => [0, LandingLayout::Single];
        yield 'one language -> single' => [1, LandingLayout::Single];
        yield 'two -> pair' => [2, LandingLayout::Pair];
        yield 'three -> trio' => [3, LandingLayout::Trio];
        yield 'four -> grid' => [4, LandingLayout::Grid];
        yield 'six -> grid' => [6, LandingLayout::Grid];
        yield 'seven -> compressed' => [7, LandingLayout::Compressed];
        yield 'nine -> compressed' => [9, LandingLayout::Compressed];
        yield 'ten -> accordion' => [10, LandingLayout::Accordion];
        yield 'twelve -> accordion' => [12, LandingLayout::Accordion];
    }

    #[DataProvider('counts')]
    public function testResolveLayout(int $count, LandingLayout $expected): void
    {
        $resolver = new ThinPickerLayoutResolver();

        static::assertSame($expected, $resolver->resolve($count));
    }
}
