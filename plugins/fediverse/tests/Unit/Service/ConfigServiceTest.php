<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\StoreInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;

class ConfigServiceTest extends TestCase
{
    /** @var array<string, Config> */
    private array $stored = [];

    public function testTheGlobalScopeReadsTheGlobalRecord(): void
    {
        // Arrange
        $this->stored[''] = new Config()
            ->setContent(Content::Full)
            ->setUsername('events');

        // Act
        $config = $this->service(null)->getConfig();

        // Assert
        static::assertSame(Content::Full, $config->getContent());
        static::assertSame('events', $config->getUsername());
    }

    public function testWithoutAnyRecordTheDefaultsApply(): void
    {
        // Act
        $config = $this->service(null)->getConfig();

        // Assert
        static::assertSame(Content::Summary, $config->getContent());
        static::assertSame(Location::City, $config->getLocation());
        static::assertTrue($config->isImage());
        static::assertTrue($config->isAnnounce());
    }

    /** @return Generator<string, array{Content, Content, Content}> */
    public static function contentCases(): Generator
    {
        yield 'a scope above the ceiling falls to it' => [Content::Full, Content::Summary, Content::Summary];
        yield 'a scope below the ceiling stays' => [Content::Title, Content::Full, Content::Title];
        yield 'a scope at the ceiling stays' => [Content::Summary, Content::Summary, Content::Summary];
    }

    #[DataProvider('contentCases')]
    public function testTheScopeContentIsClampedToTheCeiling(Content $scoped, Content $ceiling, Content $expected): void
    {
        // Arrange
        $this->stored[''] = new Config()->setContent($ceiling);
        $this->stored['7'] = new Config()->setContent($scoped);

        // Act
        $config = $this->service('7')->getConfig();

        // Assert
        static::assertSame($expected, $config->getContent());
    }

    public function testTheScopeLocationAndImageAreClampedToTheCeiling(): void
    {
        // Arrange
        $this->stored[''] = new Config()
            ->setLocation(Location::None)
            ->setImage(false);
        $this->stored['7'] = new Config()
            ->setLocation(Location::Address)
            ->setImage(true);

        // Act
        $config = $this->service('7')->getConfig();

        // Assert
        static::assertSame(Location::None, $config->getLocation());
        static::assertFalse($config->isImage());
    }

    public function testATightenedCeilingAppliesWithoutTheScopeSavingAgain(): void
    {
        // Arrange
        $this->stored[''] = new Config()->setContent(Content::Full);
        $this->stored['7'] = new Config()->setContent(Content::Full);
        $service = $this->service('7');
        $before = $service->getConfig()->getContent();

        // Act
        $this->stored[''] = new Config()->setContent(Content::Title);
        $service->reset();
        $after = $service->getConfig()->getContent();

        // Assert
        static::assertSame(Content::Full, $before);
        static::assertSame(Content::Title, $after);
    }

    public function testTheScopeKeepsItsOwnAccountAndTakesTheGlobalOnlyFieldsFromTheCeiling(): void
    {
        // Arrange
        $this->stored[''] = new Config()
            ->setUsername('events')
            ->setAnnounce(false)
            ->setBlockedServers(['bad.example']);
        $this->stored['7'] = new Config()
            ->setUsername('chess_club')
            ->setAccountSummary('We play chess.')
            ->setAnnounce(true);

        // Act
        $config = $this->service('7')->getConfig();

        // Assert
        static::assertSame('chess_club', $config->getUsername());
        static::assertSame('We play chess.', $config->getAccountSummary());
        static::assertFalse($config->isAnnounce());
        static::assertSame(['bad.example'], $config->getBlockedServers());
    }

    public function testAnUnconfiguredScopeInheritsTheCeilingButNotTheInstallationAccount(): void
    {
        // Arrange
        $this->stored[''] = new Config()
            ->setContent(Content::Full)
            ->setUsername('events')
            ->setAccountSummary('All events.');

        // Act
        $config = $this->service('7')->getConfig();

        // Assert
        static::assertSame(Content::Full, $config->getContent());
        static::assertNull($config->getUsername());
        static::assertNull($config->getAccountSummary());
    }

    public function testClampingNeverChangesTheStoredRecord(): void
    {
        // Arrange
        $this->stored[''] = new Config()->setContent(Content::Title);
        $this->stored['7'] = new Config()->setContent(Content::Full);

        // Act
        $this->service('7')->getConfig();

        // Assert
        static::assertSame(Content::Full, $this->stored['7']->getContent());
    }

    private function service(?string $scopeId): ConfigService
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('load')->willReturnCallback(fn(string $key, ?string $scope): ?Config => $this->stored[$scope ?? ''] ?? null);

        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolveScopeId')->willReturn($scopeId);
        $resolver->method('resolveStore')->willReturn($store);

        return new ConfigService($resolver);
    }
}
