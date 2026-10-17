<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\StoreCompiledThemeColours;
use App\Theme\Colors;
use App\Theme\Descriptor;
use Override;
use PHPUnit\Framework\TestCase;

class StoreCompiledThemeColoursTest extends TestCase
{
    private const string DIST = '$primary: #00d1b2; $link: #485fc7;';

    private string $stylesDir;

    #[Override]
    protected function setUp(): void
    {
        $this->stylesDir = sys_get_temp_dir() . '/theme-hotfix-' . bin2hex(random_bytes(4));
        mkdir($this->stylesDir);
        file_put_contents($this->stylesDir . '/_config.scss.dist', self::DIST);
    }

    #[Override]
    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->stylesDir . '/*') ?: []);
        rmdir($this->stylesDir);
    }

    public function testACustomCompiledPaletteIsStored(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', '$primary: #123456; $link: #485fc7;');
        $colors = $this->createMock(Colors::class);
        $colors->method('hasStoredTheme')->willReturn(false);
        $colors->expects($this->once())->method('save')->with(['color_primary' => '#123456', 'color_link' => '#485fc7']);

        // Act
        new StoreCompiledThemeColours($colors, new Descriptor($this->stylesDir))->execute();
    }

    public function testTheDistributedPaletteIsNotStored(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', self::DIST);
        $colors = $this->createMock(Colors::class);
        $colors->method('hasStoredTheme')->willReturn(false);
        $colors->expects($this->never())->method('save');

        // Act
        new StoreCompiledThemeColours($colors, new Descriptor($this->stylesDir))->execute();
    }

    public function testAStoredThemeIsNeverOverwritten(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', '$primary: #123456;');
        $colors = $this->createMock(Colors::class);
        $colors->method('hasStoredTheme')->willReturn(true);
        $colors->expects($this->never())->method('save');

        // Act
        new StoreCompiledThemeColours($colors, new Descriptor($this->stylesDir))->execute();
    }
}
