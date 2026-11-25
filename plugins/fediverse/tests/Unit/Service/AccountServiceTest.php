<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use App\Publisher\Noindex\NoindexProviderInterface;
use App\Publisher\PluginSettings\Resolver;
use App\Service\Config\ConfigService as CoreConfigService;
use App\Service\Config\PluginService;
use App\Service\Security\SecretBox;
use App\Service\Seo\NoindexService;
use App\Service\Seo\UrlOwnerService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Service\AccountService;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class AccountServiceTest extends TestCase
{
    private ?Account $live = null;
    private ?string $activeScope = null;

    /** @var list<string> */
    private array $taken = [];

    /** @var list<Account> */
    private array $persisted = [];

    private SecretBox $secretBox;

    protected function setUp(): void
    {
        $this->secretBox = new SecretBox(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    public function testAScopeWithoutAUsernameGetsNoAccount(): void
    {
        // Act
        $account = $this->service(null)->ensureForScope('7');

        // Assert
        static::assertNull($account);
        static::assertSame([], $this->persisted);
    }

    public function testTheFirstNeedCreatesTheAccountWithAWorkingKeyPair(): void
    {
        // Act
        $account = $this->service('chess_club')->ensureForScope('7');

        // Assert
        static::assertInstanceOf(Account::class, $account);
        static::assertSame([$account], $this->persisted);
        static::assertSame('7', $account->getScopeId());
        static::assertSame('chess_club', $account->getUsername());
        static::assertEquals(new DateTimeImmutable('2026-11-25T10:00:00+00:00'), $account->getCreatedAt());

        $privateKey = $this->secretBox->decrypt($account->getEncryptedPrivateKey());
        openssl_sign('payload', $signature, $privateKey, OPENSSL_ALGO_SHA256);
        static::assertSame(1, openssl_verify('payload', $signature, $account->getPublicKey(), OPENSSL_ALGO_SHA256));
    }

    public function testATakenUsernameCreatesNothing(): void
    {
        // Arrange
        $this->taken = ['chess_club'];

        // Act
        $account = $this->service('chess_club')->ensureForScope('7');

        // Assert
        static::assertNull($account);
        static::assertSame([], $this->persisted);
    }

    public function testAnUnlockedAccountFollowsARenamedUsername(): void
    {
        // Arrange
        $this->live = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act
        $account = $this->service('go_club')->ensureForScope('7');

        // Assert
        static::assertSame($this->live, $account);
        static::assertSame('go_club', $account->getUsername());
    }

    public function testALockedAccountKeepsItsUsername(): void
    {
        // Arrange
        $this->live = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'))->lock(new DateTimeImmutable('2026-11-02'));

        // Act
        $account = $this->service('go_club')->ensureForScope('7');

        // Assert
        static::assertSame('chess_club', $account?->getUsername());
    }

    public function testARenameToATakenUsernameKeepsTheOldOne(): void
    {
        // Arrange
        $this->live = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));
        $this->taken = ['go_club'];

        // Act
        $account = $this->service('go_club')->ensureForScope('7');

        // Assert
        static::assertSame('chess_club', $account?->getUsername());
    }

    public function testAClearedUsernameIsNoAccountToPublishWithButRetiresNothing(): void
    {
        // Arrange
        $this->live = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act
        $account = $this->service(null)->ensureForScope('7');

        // Assert
        static::assertNull($account);
        static::assertFalse($this->live->isRetired(), 'the reconcile leaves it, sending its deletes');
    }

    public function testAScopeWithSharingSwitchedOffGetsNoAccountAndDoesNotFederate(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act
        $service = $this->service('chess_club', switchedOn: false);

        // Assert
        static::assertNull($service->ensureForScope('7'));
        static::assertSame([], $this->persisted);
        static::assertFalse($service->isFederating($account));
    }

    public function testTheInstallationAccountFederatesWhileItHasAUsername(): void
    {
        // Arrange
        $account = new Account(null, 'events', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act + Assert
        static::assertTrue($this->service('events')->isFederating($account));
        static::assertFalse($this->service(null)->isFederating($account));
    }

    /** @return Generator<string, array{?string, string, bool, bool, bool}> */
    public static function scopes(): Generator
    {
        yield 'every level said yes' => ['chess_club', '7', true, false, true];
        yield 'no username' => [null, '7', true, false, false];
        yield 'the scope did not take effect' => ['chess_club', '8', true, false, false];
        yield 'the plugin is off in the scope' => ['chess_club', '7', false, false, false];
        yield 'the scope is not indexable' => ['chess_club', '7', true, true, false];
    }

    #[DataProvider('scopes')]
    public function testAScopedAccountFederatesOnlyWhileEveryLevelSaysYes(
        ?string $username,
        string $scopeInEffect,
        bool $pluginActive,
        bool $noindex,
        bool $expected,
    ): void {
        // Arrange
        $account = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act
        $isFederating = $this->service($username, $scopeInEffect, $pluginActive, $noindex)->isFederating($account);

        // Assert
        static::assertSame($expected, $isFederating);
        static::assertNull($this->activeScope);
    }

    private function service(
        ?string $username,
        string $scopeInEffect = '7',
        bool $pluginActive = true,
        bool $noindex = false,
        bool $switchedOn = true,
    ): AccountService {
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
        $resolver->method('resolveScopeId')->willReturnCallback(fn(): ?string => $this->activeScope === null ? null : $scopeInEffect);

        $plugins = $this->createStub(PluginService::class);
        $plugins->method('getActiveList')->willReturn($pluginActive ? ['fediverse'] : []);

        $noindexProvider = $this->createStub(NoindexProviderInterface::class);
        $noindexProvider->method('shouldNoindex')->willReturn($noindex);

        $repository = $this->createStub(AccountRepository::class);
        $repository->method('findLiveForScope')->willReturnCallback(fn(): ?Account => $this->live);
        $repository->method('isUsernameTakenOutsideScope')->willReturnCallback(fn(string $name): bool => in_array($name, $this->taken, true));

        $configService = $this->createStub(ConfigService::class);
        $configService
            ->method('getConfigFor')
            ->willReturn(
                new Config()
                    ->setUsername($username)
                    ->setActive($switchedOn),
            );

        $coreConfig = $this->createStub(CoreConfigService::class);
        $coreConfig->method('getHost')->willReturn('https://events.example');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            \assert($entity instanceof Account);
            $this->persisted[] = $entity;
        });

        return new AccountService(
            $repository,
            $configService,
            $this->secretBox,
            $resolver,
            $plugins,
            new NoindexService([$noindexProvider]),
            new UrlOwnerService($coreConfig, []),
            $em,
            new LockFactory(new InMemoryStore()),
            new MockClock('2026-11-25T10:00:00+00:00'),
            new NullLogger(),
        );
    }
}
