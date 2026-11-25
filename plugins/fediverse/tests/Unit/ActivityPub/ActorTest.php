<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\ActivityPub;

use App\Publisher\PluginSettings\Resolver;
use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService as CoreConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Media\SiteIconResolver;
use App\Service\Media\SiteLogoResolver;
use App\Service\Seo\UrlOwnerService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Controller\ActorController;
use Plugin\Fediverse\Controller\InboxController;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ActorTest extends TestCase
{
    private ?string $activeScope = null;

    public function testTheInstallationAccountIsAnApplicationOnTheAccountHost(): void
    {
        // Arrange
        $account = new Account(null, 'events', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01T10:00:00+00:00'));

        // Act
        $document = $this->actor()->document($account);

        // Assert
        static::assertSame('https://events.example/fediverse/@events', $document['id']);
        static::assertSame('Application', $document['type']);
        static::assertSame('events', $document['preferredUsername']);
        static::assertSame('Installation', $document['name']);
        static::assertSame('https://events.example/', $document['url']);
        static::assertSame('https://events.example/fediverse/@events/inbox', $document['inbox']);
        static::assertSame('https://events.example/fediverse/inbox', $document['endpoints']['sharedInbox']);
        static::assertSame(
            [
                'id' => 'https://events.example/fediverse/@events#main-key',
                'owner' => 'https://events.example/fediverse/@events',
                'publicKeyPem' => 'PUBLIC KEY',
            ],
            $document['publicKey'],
        );
        static::assertSame(['type' => 'Image', 'url' => 'https://events.example/icon.png?v1'], $document['icon']);
    }

    public function testAScopedAccountIsAGroupRenderedInsideItsScopeButLivesOnTheAccountHost(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));

        // Act
        $document = $this->actor()->document($account);

        // Assert
        static::assertSame('https://events.example/fediverse/@chess_club', $document['id']);
        static::assertSame('Group', $document['type']);
        static::assertSame('Scope 7', $document['name']);
        static::assertSame('https://chess.example/', $document['url']);
        static::assertSame(['type' => 'Image', 'url' => 'https://chess.example/icon.png?v1'], $document['icon']);
        static::assertSame('<p>We play &lt;b&gt;chess&lt;/b&gt;.<br />
Every week.</p>', $document['summary']);
        static::assertNull($this->activeScope);
    }

    public function testARetiredAccountBecomesATombstone(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'))->retire(
            new DateTimeImmutable('2026-11-20T08:00:00+00:00'),
        );

        // Act
        $tombstone = $this->actor()->tombstone($account);

        // Assert
        static::assertSame('Tombstone', $tombstone['type']);
        static::assertSame('Group', $tombstone['formerType']);
        static::assertSame('https://events.example/fediverse/@chess_club', $tombstone['id']);
        static::assertSame('2026-11-20T08:00:00+00:00', $tombstone['deleted']);
    }

    public function testTheHandleNamesTheAccountHost(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));

        // Act + Assert
        static::assertSame('chess_club@events.example', $this->actor()->handle($account));
    }

    /** @return Generator<string, array{string, ?string}> */
    public static function actorIds(): Generator
    {
        yield 'an account of ours' => ['https://events.example/fediverse/@chess_club', 'chess_club'];
        yield 'another host' => ['https://chess.example/fediverse/@chess_club', null];
        yield 'a path below the actor' => ['https://events.example/fediverse/@chess_club/inbox', null];
        yield 'a remote actor' => ['https://social.example/users/chess_club', null];
        yield 'a username the route does not allow' => ['https://events.example/fediverse/@Chess-Club', null];
        yield 'our prefix below another path' => ['https://events.example/other/fediverse/@chess_club', null];
    }

    #[DataProvider('actorIds')]
    public function testOnlyAnActorIdOfOursNamesAUsername(string $actorId, ?string $username): void
    {
        // Act + Assert
        static::assertSame($username, $this->actor()->usernameOf($actorId));
    }

    /** @return Generator<string, array{string}> */
    public static function usernames(): Generator
    {
        yield 'empty' => [''];
        yield 'a letter' => ['a'];
        yield 'letters, digits and underscores' => ['chess_club_42'];
        yield 'at the maximum' => [str_repeat('a', Config::USERNAME_MAX)];
        yield 'past the maximum' => [str_repeat('a', Config::USERNAME_MAX + 1)];
        yield 'capitals' => ['Chess'];
        yield 'a dash' => ['chess-club'];
        yield 'a dot' => ['chess.club'];
        yield 'a non-ascii letter' => ['schach_über'];
    }

    #[DataProvider('usernames')]
    public function testEveryUsernameRouteAcceptsExactlyWhatTheConfigAccepts(string $username): void
    {
        // Arrange
        $requirements = [];
        foreach ([ActorController::class, InboxController::class] as $controller) {
            foreach (new ReflectionClass($controller)->getMethods() as $method) {
                foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                    $requirement = $attribute->newInstance()->requirements['username'] ?? null;
                    if (is_string($requirement)) {
                        $requirements[] = $requirement;
                    }
                }
            }
        }
        static::assertCount(4, $requirements, 'actor, followers, outbox and the per-account inbox');

        // Act + Assert
        foreach ($requirements as $requirement) {
            static::assertSame(Config::isUsername($username), preg_match('#^(?:' . $requirement . ')$#', $username) === 1, $requirement);
        }
    }

    private function actor(): Actor
    {
        $resolver = $this->createStub(Resolver::class);
        $resolver
            ->method('runInScope')
            ->willReturnCallback(function (string $scopeId, callable $work): mixed {
                $this->activeScope = $scopeId;
                try {
                    return $work();
                } finally {
                    $this->activeScope = null;
                }
            });

        $coreConfig = $this->createStub(CoreConfigService::class);
        $coreConfig->method('getHost')->willReturn('https://events.example/');

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfigFor')->willReturnCallback(static fn(?string $scopeId): Config => $scopeId === null
            ? new Config()
            : new Config()->setAccountSummary("We play <b>chess</b>.\nEvery week."));

        $siteName = $this->createStub(SiteNameResolver::class);
        $siteName->method('resolve')->willReturnCallback(fn(): string => $this->activeScope === null ? 'Installation' : 'Scope ' . $this->activeScope);

        $siteIcon = $this->createStub(SiteIconResolver::class);
        $siteIcon->method('url')->willReturn('/icon.png?v1');

        $owner = new class($this) implements UrlOwnerProviderInterface {
            public function __construct(
                private readonly ActorTest $test,
            ) {}

            public function getOwnerHost(string $route, array $parameters): ?string
            {
                return $this->test->activeScope() === null ? null : 'https://chess.example';
            }
        };

        return new Actor(
            $resolver,
            $coreConfig,
            $configService,
            $siteName,
            $siteIcon,
            $this->createStub(SiteLogoResolver::class),
            new UrlOwnerService($coreConfig, [$owner]),
            $this->createStub(FollowerRepository::class),
            new UrlGenerator($this->routes(), new RequestContext()),
        );
    }

    private function routes(): RouteCollection
    {
        $username = ['username' => '[a-z0-9_]{1,30}'];
        $routes = new RouteCollection();
        $routes->add('app_plugin_fediverse_actor', new Route('/fediverse/@{username}', requirements: $username));
        $routes->add('app_plugin_fediverse_actor_inbox', new Route('/fediverse/@{username}/inbox', requirements: $username));
        $routes->add('app_plugin_fediverse_outbox', new Route('/fediverse/@{username}/outbox', requirements: $username));
        $routes->add('app_plugin_fediverse_followers', new Route('/fediverse/@{username}/followers', requirements: $username));
        $routes->add('app_plugin_fediverse_inbox', new Route('/fediverse/inbox'));

        return $routes;
    }

    public function activeScope(): ?string
    {
        return $this->activeScope;
    }
}
