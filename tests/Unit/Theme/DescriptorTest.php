<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Theme\Descriptor;
use Override;
use PHPUnit\Framework\TestCase;

class DescriptorTest extends TestCase
{
    private string $stylesDir = '';

    #[Override]
    protected function setUp(): void
    {
        $this->stylesDir = sys_get_temp_dir() . '/descriptor-test-' . uniqid('', true);
        mkdir($this->stylesDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->stylesDir . '/_config.scss');
        rmdir($this->stylesDir);
    }

    public function testAThreeDigitColourInTheCompiledConfigIsExpanded(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', "\$primary: #0aF;\n\$link: #485fc7;\n");

        // Act
        $baseline = new Descriptor($this->stylesDir)->compiledBaseline();

        // Assert
        static::assertSame('#00aaff', $baseline->get('color_primary'));
        static::assertSame('#485fc7', $baseline->get('color_link'));
    }
}
