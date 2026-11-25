<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Config\PluginService;
use App\Service\Security\SecretBox;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testAClearedUsernameKeepsTheExistingAccount(): void
    {
        // Arrange
        $this->live = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));

        // Act
        $account = $this->service(null)->ensureForScope('7');

        // Assert
        static::assertSame($this->live, $account);
    }

    private function service(?string $username): AccountService
    {
        $repository = $this->createStub(AccountRepository::class);
        $repository->method('findLiveForScope')->willReturnCallback(fn(): ?Account => $this->live);
        $repository->method('isUsernameTakenOutsideScope')->willReturnCallback(fn(string $name): bool => in_array($name, $this->taken, true));

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfigFor')->willReturn(new Config()->setUsername($username));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            \assert($entity instanceof Account);
            $this->persisted[] = $entity;
        });

        return new AccountService(
            $repository,
            $configService,
            $this->secretBox,
            $this->createStub(Resolver::class),
            $this->createStub(PluginService::class),
            $em,
            new LockFactory(new InMemoryStore()),
            new MockClock('2026-11-25T10:00:00+00:00'),
            new NullLogger(),
        );
    }
}
