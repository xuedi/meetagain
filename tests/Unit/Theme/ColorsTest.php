<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Publisher\PluginSettings\StoreInterface;
use App\Service\Admin\PluginSettingsService;
use App\Theme\Colors;
use App\Theme\Config;
use App\Theme\CssRenderer;
use App\Theme\Descriptor;
use App\Theme\Store;
use ArrayObject;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class ColorsTest extends TestCase
{
    private string $stylesDir = '';

    /** @var ArrayObject<string, object> */
    private ArrayObject $records;

    /** @var ArrayObject<string, ?string> */
    private ArrayObject $request;

    #[Override]
    protected function setUp(): void
    {
        $this->stylesDir = sys_get_temp_dir() . '/colors-test-' . uniqid('', true);
        mkdir($this->stylesDir);
        file_put_contents($this->stylesDir . '/_config.scss', "\$primary: #00d1b2;\n\$link: #485fc7;\n");
        $this->records = new ArrayObject();
        $this->request = new ArrayObject(['scope' => null]);
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
        $colors->save(['color_primary' => '#123456'], null);

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
        $colors->save(['color_primary' => '#123456'], null);

        // Assert
        static::assertSame('#00d1b2', $before['color_primary']);
        static::assertSame('#123456', $colors->all()['color_primary']);
    }

    public function testAnInvalidColourKeepsTheSavedOne(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_primary' => '#123456'], null);

        // Act
        $colors->save(['color_primary' => 'red'], null);

        // Assert
        static::assertSame('#123456', $colors->all()['color_primary']);
    }

    public function testAScopeLaysItsRecordOverTheGlobalOneOverTheBaseline(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_primary' => '#111111'], null);

        // Act
        $colors->save(['color_link' => '#222222'], '7');

        // Assert
        static::assertSame(['color_primary' => '#111111', 'color_link' => '#222222'], $colors->forScope('7'));
        static::assertSame(['color_primary' => '#111111', 'color_link' => '#485fc7'], $colors->forScope(null));
    }

    public function testAScopeSaveStoresOnlyTheColoursThatDifferFromTheGlobalOnes(): void
    {
        // Arrange
        $colors = $this->colors();

        // Act
        $colors->save(['color_primary' => '#00d1b2', 'color_link' => '#222222'], '7');

        // Assert
        $record = $this->records['7'] ?? null;
        static::assertInstanceOf(Config::class, $record);
        static::assertSame(['color_link' => '#222222'], $record->toArray());
    }

    public function testAColourTheScopeLeftAloneFollowsALaterGlobalChange(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_link' => '#222222'], '7');
        $colors->forScope('7');

        // Act
        $colors->save(['color_primary' => '#333333'], null);

        // Assert
        static::assertSame(['color_primary' => '#333333', 'color_link' => '#222222'], $colors->forScope('7'));
    }

    public function testAResetScopeInheritsEveryGlobalColour(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_primary' => '#111111', 'color_link' => '#222222'], '7');

        // Act
        $colors->reset('7');

        // Assert
        static::assertSame($colors->forScope(null), $colors->forScope('7'));
    }

    public function testTheInlineStyleFollowsTheRequestScope(): void
    {
        // Arrange
        $colors = $this->colors();
        $colors->save(['color_link' => '#123456'], '7');

        // Act
        $global = $colors->inlineStyle();
        $this->request['scope'] = '7';
        $scoped = $colors->inlineStyle();

        // Assert
        static::assertSame('', $global);
        static::assertStringContainsString('--bulma-link-h: 210deg;', $scoped);
    }

    public function testOnlyAWrittenRecordCountsAsAStoredTheme(): void
    {
        // Arrange
        $colors = $this->colors();

        // Act
        $colors->reset('7');

        // Assert
        static::assertFalse($colors->hasStoredTheme(null));
        static::assertTrue($colors->hasStoredTheme('7'));
    }

    private function colors(): Colors
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $store = new Store([$this->store(scoped: false), $this->store(scoped: true)], $cache);
        $descriptor = new Descriptor($this->stylesDir);

        $resolver = new Resolver(new PluginSettingsService([$descriptor]), [], [$this->scopeProvider()]);

        return new Colors($resolver, $store, $descriptor, new CssRenderer(), $cache);
    }

    private function store(bool $scoped): StoreInterface
    {
        return new class($scoped, $this->records) implements StoreInterface {
            /**
             * @param ArrayObject<string, object> $records
             */
            public function __construct(
                private readonly bool $scoped,
                private readonly ArrayObject $records,
            ) {}

            public function supports(string $key, ?string $scopeId): bool
            {
                return $this->scoped === ($scopeId !== null);
            }

            public function load(string $key, ?string $scopeId): ?object
            {
                return $this->records[$scopeId ?? 'global'] ?? null;
            }

            public function save(string $key, object $data, ?string $scopeId): void
            {
                $this->records[$scopeId ?? 'global'] = $data;
            }

            public function getPriority(): int
            {
                return 0;
            }
        };
    }

    private function scopeProvider(): ScopeProviderInterface
    {
        return new class($this->request) implements ScopeProviderInterface {
            /**
             * @param ArrayObject<string, ?string> $request
             */
            public function __construct(
                private readonly ArrayObject $request,
            ) {}

            public function getScopeId(): ?string
            {
                return $this->request['scope'];
            }
        };
    }
}
