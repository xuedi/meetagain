<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Api;

use App\Entity\EventListItemTag;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Exception\OpenApiCollisionException;
use App\Plugin;
use App\Service\Api\OpenApiSpecBuilder;
use App\ValueObject\LinkCollection;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \App\Service\Api\OpenApiSpecBuilder
 * @covers \App\Exception\OpenApiCollisionException
 */
final class OpenApiSpecBuilderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/openapi-builder-' . uniqid();
        mkdir($this->fixtureDir . '/config/api', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->fixtureDir);
    }

    public function testReturnsCoreSpecWhenNoPluginsContribute(): void
    {
        // Arrange
        $this->writeCoreSpec(['openapi' => '3.1.0', 'paths' => ['/api/x' => ['get' => []]]]);
        $builder = new OpenApiSpecBuilder([], new ArrayAdapter(), $this->fixtureDir);

        // Act
        $spec = $builder->build();

        // Assert
        static::assertSame(['/api/x'], array_keys($spec['paths']));
    }

    public function testMergesPluginPathsAndSchemas(): void
    {
        // Arrange
        $this->writeCoreSpec([
            'paths' => ['/api/core' => ['get' => []]],
            'components' => ['schemas' => ['CoreThing' => ['type' => 'object']]],
        ]);
        $plugin = new FakePlugin('demo', [
            'paths' => ['/api/groups' => ['get' => ['summary' => 'List groups']]],
            'components' => ['schemas' => ['Group' => ['type' => 'object']]],
        ]);
        $builder = new OpenApiSpecBuilder([$plugin], new ArrayAdapter(), $this->fixtureDir);

        // Act
        $spec = $builder->build();

        // Assert
        static::assertSame(['/api/core', '/api/groups'], array_keys($spec['paths']));
        static::assertSame(['CoreThing', 'Group'], array_keys($spec['components']['schemas']));
        static::assertSame('List groups', $spec['paths']['/api/groups']['get']['summary']);
    }

    public function testThrowsOnPathCollisionBetweenTwoPlugins(): void
    {
        // Arrange
        $this->writeCoreSpec([]);
        $a = new FakePlugin('alpha', ['paths' => ['/api/x' => ['get' => []]]]);
        $b = new FakePlugin('beta', ['paths' => ['/api/x' => ['post' => []]]]);
        $builder = new OpenApiSpecBuilder([$a, $b], new ArrayAdapter(), $this->fixtureDir);

        // Act + Assert
        $this->expectException(OpenApiCollisionException::class);
        $this->expectExceptionMessageMatches("/path collision: \\/api\\/x.*'alpha'.*'beta'/");
        $builder->build();
    }

    public function testThrowsOnPathCollisionBetweenCoreAndPlugin(): void
    {
        // Arrange
        $this->writeCoreSpec(['paths' => ['/api/x' => ['get' => []]]]);
        $plugin = new FakePlugin('demo', ['paths' => ['/api/x' => ['post' => []]]]);
        $builder = new OpenApiSpecBuilder([$plugin], new ArrayAdapter(), $this->fixtureDir);

        // Act + Assert
        $this->expectException(OpenApiCollisionException::class);
        $this->expectExceptionMessageMatches("/path collision: \\/api\\/x.*'core'.*'demo'/");
        $builder->build();
    }

    public function testThrowsOnSchemaCollision(): void
    {
        // Arrange
        $this->writeCoreSpec([]);
        $a = new FakePlugin('alpha', ['components' => ['schemas' => ['Foo' => ['type' => 'object']]]]);
        $b = new FakePlugin('beta', ['components' => ['schemas' => ['Foo' => ['type' => 'object']]]]);
        $builder = new OpenApiSpecBuilder([$a, $b], new ArrayAdapter(), $this->fixtureDir);

        // Act + Assert
        $this->expectException(OpenApiCollisionException::class);
        $this->expectExceptionMessageMatches("/schema collision: Foo.*'alpha'.*'beta'/");
        $builder->build();
    }

    public function testThrowsOnTagCollision(): void
    {
        // Arrange
        $this->writeCoreSpec(['tags' => [['name' => 'data', 'description' => 'core data']]]);
        $plugin = new FakePlugin('demo', ['tags' => [['name' => 'data', 'description' => 'plugin data']]]);
        $builder = new OpenApiSpecBuilder([$plugin], new ArrayAdapter(), $this->fixtureDir);

        // Act + Assert
        $this->expectException(OpenApiCollisionException::class);
        $this->expectExceptionMessageMatches("/tag collision: 'data'.*'core'.*'demo'/");
        $builder->build();
    }

    public function testIgnoresPluginsThatReturnEmptyArray(): void
    {
        // Arrange
        $this->writeCoreSpec(['paths' => ['/api/core' => ['get' => []]]]);
        $emptyPlugin = new FakePlugin('navigation', []);
        $builder = new OpenApiSpecBuilder([$emptyPlugin], new ArrayAdapter(), $this->fixtureDir);

        // Act
        $spec = $builder->build();

        // Assert
        static::assertSame(['/api/core'], array_keys($spec['paths']));
    }

    public function testCachesResultAcrossCalls(): void
    {
        // Arrange — a plugin that throws if asked twice. Two build() calls must hit the cache after the first.
        $this->writeCoreSpec([]);
        $plugin = new SpyPlugin('spy', ['paths' => ['/api/x' => ['get' => []]]]);
        $builder = new OpenApiSpecBuilder([$plugin], new ArrayAdapter(), $this->fixtureDir);

        // Act
        $builder->build();
        $builder->build();

        // Assert
        static::assertSame(1, $plugin->fragmentCalls, 'Plugin fragment must only be loaded once');
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function writeCoreSpec(array $spec): void
    {
        file_put_contents(
            $this->fixtureDir . '/config/api/openapi.json',
            json_encode($spec, JSON_THROW_ON_ERROR),
        );
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

class FakePlugin implements Plugin
{
    /**
     * @param array<string, mixed> $fragment
     */
    public function __construct(private string $key, protected array $fragment) {}

    #[Override] public function getPluginKey(): string
    {
        return $this->key;
    }
    #[Override] public function getOpenApiFragment(): array
    {
        return $this->fragment;
    }
    #[Override] public function getLinkCollection(): LinkCollection
    {
        return new LinkCollection();
    }
    #[Override] public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }
    #[Override] public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }
    #[Override] public function preFixtures(OutputInterface $output): void
    {
    }
    #[Override] public function postFixtures(OutputInterface $output): void
    {
    }
    #[Override] public function getFooterAbout(): ?string
    {
        return null;
    }
    /** @return list<EventListItemTag> */
    #[Override] public function getEventListItemTags(int $eventId): array
    {
        return [];
    }
    #[Override] public function warmCache(WarmCacheType $type, array $ids): void
    {
    }
    /** @return list<string> */
    #[Override] public function getStylesheets(): array
    {
        return [];
    }
    /** @return list<string> */
    #[Override] public function getJavascripts(): array
    {
        return [];
    }
}

final class SpyPlugin extends FakePlugin
{
    public int $fragmentCalls = 0;

    #[Override] public function getOpenApiFragment(): array
    {
        $this->fragmentCalls++;

        return $this->fragment;
    }
}
