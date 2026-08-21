<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\HeadHtml;

use App\Publisher\HeadHtml\HeadHtmlProviderInterface;
use App\Publisher\HeadHtml\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    /**
     * @param list<?string> $fragments
     */
    #[DataProvider('provideRenderCases')]
    public function testRenderCombinesProviderFragments(array $fragments, string $expected): void
    {
        // Arrange
        $providers = array_map($this->stubProvider(...), $fragments);

        // Act
        $rendered = (new Registry($providers))->render();

        // Assert
        static::assertSame($expected, $rendered);
    }

    public static function provideRenderCases(): iterable
    {
        yield 'no providers registered' => [[], ''];
        yield 'null contributes nothing' => [[null], ''];
        yield 'whitespace only contributes nothing' => [["  \n "], ''];
        yield 'single fragment passes through' => [['<meta name="a">'], '<meta name="a">'];
        yield 'surrounding whitespace is trimmed' => [['  <meta name="a">  '], '<meta name="a">'];
        yield 'fragments are newline separated' => [
            ['<meta name="a">', '<meta name="b">'],
            "<meta name=\"a\">\n<meta name=\"b\">",
        ];
        yield 'a silent provider does not leave a blank line' => [
            ['<meta name="a">', null, '<meta name="b">'],
            "<meta name=\"a\">\n<meta name=\"b\">",
        ];
    }

    private function stubProvider(?string $fragment): HeadHtmlProviderInterface
    {
        $provider = $this->createStub(HeadHtmlProviderInterface::class);
        $provider->method('getHeadHtml')->willReturn($fragment);

        return $provider;
    }
}
