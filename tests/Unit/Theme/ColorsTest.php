<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\StoreInterface;
use App\Theme\Colors;
use App\Theme\CssRenderer;
use App\Theme\Descriptor;
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

    private function colors(): Colors
    {
        $store = new class implements StoreInterface {
            private ?object $data = null;

            #[Override]
            public function supports(string $key, ?string $scopeId): bool
            {
                return true;
            }

            #[Override]
            public function load(string $key, ?string $scopeId): ?object
            {
                return $this->data;
            }

            #[Override]
            public function save(string $key, object $data, ?string $scopeId): void
            {
                $this->data = $data;
            }

            #[Override]
            public function getPriority(): int
            {
                return 0;
            }
        };
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolveStore')->willReturn($store);

        return new Colors($resolver, new Descriptor($this->stylesDir), new CssRenderer(), new ArrayAdapter());
    }
}
