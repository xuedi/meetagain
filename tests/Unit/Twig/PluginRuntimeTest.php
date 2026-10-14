<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Publisher\Footer\BarProviderInterface;
use App\Service\Config\PluginService;
use App\Twig\PluginRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class PluginRuntimeTest extends TestCase
{
    /**
     * @param list<?string> $fragments
     */
    #[DataProvider('provideFooterBarCases')]
    public function testFooterBarHtmlCombinesProviderFragments(array $fragments, string $expected): void
    {
        // Arrange
        $runtime = new PluginRuntime(
            [],
            $this->createStub(PluginService::class),
            $this->createStub(Environment::class),
            array_map($this->stubProvider(...), $fragments),
        );

        // Act
        $html = $runtime->getFooterBarHtml();

        // Assert
        static::assertSame($expected, $html);
    }

    public static function provideFooterBarCases(): iterable
    {
        yield 'no providers registered' => [[], ''];
        yield 'null contributes nothing' => [[null], ''];
        yield 'whitespace only contributes nothing' => [["  \n "], ''];
        yield 'surrounding whitespace is trimmed' => [['  <div>a</div>  '], '<div>a</div>'];
        yield 'a silent provider does not leave a blank line' => [
            ['<div>a</div>', null, '<div>b</div>'],
            "<div>a</div>\n<div>b</div>",
        ];
    }

    private function stubProvider(?string $fragment): BarProviderInterface
    {
        $provider = $this->createStub(BarProviderInterface::class);
        $provider->method('getFooterBarHtml')->willReturn($fragment);

        return $provider;
    }
}
