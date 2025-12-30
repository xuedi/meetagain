<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\FormatBytesExtension;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatBytesExtensionTest extends TestCase
{
    private FormatBytesExtension $subject;

    protected function setUp(): void
    {
        $this->subject = new FormatBytesExtension();
    }

    public function testGetFiltersReturnsFormatBytesFilter(): void
    {
        $filters = $this->subject->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('format_bytes', $filters[0]->getName());
    }

    #[DataProvider('bytesProvider')]
    public function testFormatBytes(int $bytes, string $expected): void
    {
        $this->assertSame($expected, $this->subject->formatBytes($bytes));
    }

    public static function bytesProvider(): Generator
    {
        yield 'zero bytes' => [0, '0 B'];
        yield 'one byte' => [1, '1 B'];
        yield 'small bytes' => [512, '512 B'];
        yield 'one KiB' => [1024, '1 KiB'];
        yield 'fractional KiB' => [1536, '1.5 KiB'];
        yield 'one MiB' => [1048576, '1 MiB'];
        yield 'one GiB' => [1073741824, '1 GiB'];
        yield 'one TiB' => [1099511627776, '1 TiB'];
        yield 'realistic file size' => [15728640, '15 MiB'];
        yield 'negative becomes zero' => [-100, '0 B'];
    }
}
