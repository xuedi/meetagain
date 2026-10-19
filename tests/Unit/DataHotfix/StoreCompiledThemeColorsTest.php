<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\StoreCompiledThemeColors;
use App\Theme\Colors;
use App\Theme\Descriptor;
use Override;
use PHPUnit\Framework\TestCase;

class StoreCompiledThemeColorsTest extends TestCase
{
    private const string DIST = '$primary: #00d1b2; $link: #485fc7;';

    private string $stylesDir;

    /** @var array<array-key, mixed>|null */
    private ?array $saved = null;

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

        // Act
        new StoreCompiledThemeColors($this->colors(hasStoredTheme: false), new Descriptor($this->stylesDir))->execute();

        // Assert
        static::assertSame(['color_primary' => '#123456', 'color_link' => '#485fc7'], $this->saved);
    }

    public function testTheDistributedPaletteIsNotStored(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', self::DIST);

        // Act
        new StoreCompiledThemeColors($this->colors(hasStoredTheme: false), new Descriptor($this->stylesDir))->execute();

        // Assert
        static::assertNull($this->saved);
    }

    public function testAStoredThemeIsNeverOverwritten(): void
    {
        // Arrange
        file_put_contents($this->stylesDir . '/_config.scss', '$primary: #123456;');

        // Act
        new StoreCompiledThemeColors($this->colors(hasStoredTheme: true), new Descriptor($this->stylesDir))->execute();

        // Assert
        static::assertNull($this->saved);
    }

    private function colors(bool $hasStoredTheme): Colors
    {
        $colors = $this->createStub(Colors::class);
        $colors->method('hasStoredTheme')->willReturn($hasStoredTheme);
        $colors
            ->method('save')
            ->willReturnCallback(function (array $saved): void {
                $this->saved = $saved;
            });

        return $colors;
    }
}
