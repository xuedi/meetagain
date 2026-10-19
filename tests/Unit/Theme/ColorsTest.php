<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Publisher\PluginSettings\GenericStore;
use App\Publisher\PluginSettings\Resolver;
use App\Theme\Colors;
use App\Theme\CssRenderer;
use App\Theme\Descriptor;
use App\Theme\Store;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ColorsTest extends TestCase
{
    private string $stylesDir = '';

    #[Override]
    protected function setUp(): void
    {
        $this->stylesDir = sys_get_temp_dir() . '/colors-test-' . uniqid('', true);
        mkdir($this->stylesDir);
        file_put_contents($this->stylesDir . '/_config.scss', "\$primary: #00d1b2;\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->stylesDir . '/_config.scss');
        rmdir($this->stylesDir);
    }

    public function testASavedColourReplacesTheCachedStyle(): void
    {
        // Arrange
        $colors = $this->colors();
        $before = $colors->inlineStyle();

        // Act
        $colors->save(['color_primary' => '#123456']);

        // Assert
        static::assertSame('', $before);
        static::assertStringContainsString('--bulma-primary-h: 210deg;', $colors->inlineStyle());
    }

    public function testASavedColourReplacesTheCachedColours(): void
    {
        // Arrange
        $colors = $this->colors();
        $before = $colors->all();

        // Act
        $colors->save(['color_primary' => '#123456']);

        // Assert
        static::assertSame('#00d1b2', $before['color_primary']);
        static::assertSame('#123456', $colors->all()['color_primary']);
    }

    public function testAnInvalidColourKeepsTheSavedOne(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_primary' => '#123456']);

        // Act
        $colors->save(['color_primary' => 'red']);

        // Assert
        static::assertSame('#123456', $colors->all()['color_primary']);
    }

    private function colors(): Colors
    {
        $saved = null;
        $records = $this->createStub(GenericStore::class);
        $records
            ->method('load')
            ->willReturnCallback(static function () use (&$saved): ?object {
                return $saved;
            });
        $records
            ->method('save')
            ->willReturnCallback(static function (string $key, object $data) use (&$saved): void {
                $saved = $data;
            });
        $cache = new ArrayAdapter();
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolveStore')->willReturn(new Store($records, $cache));

        return new Colors($resolver, new Descriptor($this->stylesDir), new CssRenderer(), $cache);
    }
}
