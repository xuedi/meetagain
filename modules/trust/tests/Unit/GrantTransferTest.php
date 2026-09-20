<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Trust\Contract\PortableGrant;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\Entity\TrustGrant;
use Module\Trust\Internal\GrantTransfer;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Module\Trust\Internal\ScoreCalculator;
use Module\Trust\Internal\ScoreProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class GrantTransferTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    public function testExportedGrantsCarryTheirMembersLevelAndDates(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-01-05 10:00');
        $updatedAt = new DateTimeImmutable('2026-02-01 12:00');
        $grant = new TrustGrant('book', $this->user(3), $this->user(4), TrustLevel::Slight, $createdAt);
        $grant->setLevel(TrustLevel::Trusted, $updatedAt);
        $repository = $this->createStub(TrustGrantRepository::class);
        $repository->method('findForContexts')->willReturn([$grant]);

        // Act
        $exported = $this->transfer($repository)->exportGrants(['book']);

        // Assert
        static::assertEquals([new PortableGrant('book', 3, 4, TrustLevel::Trusted, $createdAt, $updatedAt)], $exported);
    }

    public function testNoContextsExportNothing(): void
    {
        // Arrange
        $repository = $this->createMock(TrustGrantRepository::class);
        $repository->expects($this->never())->method('findForContexts');

        // Act
        $exported = $this->transfer($repository)->exportGrants([]);

        // Assert
        static::assertSame([], $exported);
    }

    public function testANewEdgeKeepsItsArchivedDatesAndDropsTheCachedScores(): void
    {
        // Arrange
        $cache = $this->createMock(AdapterInterface::class);
        $cache->expects($this->once())->method('deleteItem');
        $repository = $this->createStub(TrustGrantRepository::class);
        $repository->method('findEdge')->willReturn(null);
        $createdAt = new DateTimeImmutable('2026-01-05 10:00');
        $updatedAt = new DateTimeImmutable('2026-02-01 12:00');

        // Act
        $this->transfer($repository, $cache)->restoreGrant(new PortableGrant('book', 3, 4, TrustLevel::Absolute, $createdAt, $updatedAt));

        // Assert
        static::assertCount(1, $this->persisted);
        $restored = $this->persisted[0];
        static::assertInstanceOf(TrustGrant::class, $restored);
        static::assertSame('book', $restored->getContext());
        static::assertSame(3, $restored->getFromUser()->getId());
        static::assertSame(4, $restored->getToUser()->getId());
        static::assertSame(TrustLevel::Absolute, $restored->getLevel());
        static::assertEquals($createdAt, $restored->getCreatedAt());
        static::assertEquals($updatedAt, $restored->getUpdatedAt());
    }

    public function testAnExistingEdgeTakesTheArchivedLevel(): void
    {
        // Arrange
        $existing = new TrustGrant('book', $this->user(3), $this->user(4), TrustLevel::Slight, new DateTimeImmutable('2025-12-01'));
        $repository = $this->createStub(TrustGrantRepository::class);
        $repository->method('findEdge')->willReturn($existing);
        $updatedAt = new DateTimeImmutable('2026-02-01 12:00');

        // Act
        $this->transfer($repository)->restoreGrant(new PortableGrant('book', 3, 4, TrustLevel::Trusted, new DateTimeImmutable('2026-01-05'), $updatedAt));

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(TrustLevel::Trusted, $existing->getLevel());
        static::assertEquals($updatedAt, $existing->getUpdatedAt());
    }

    public function testASelfEdgeIsRefused(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->transfer($this->createStub(TrustGrantRepository::class))->restoreGrant(
            new PortableGrant('book', 3, 3, TrustLevel::Slight, new DateTimeImmutable(), new DateTimeImmutable()),
        );
    }

    private function transfer(TrustGrantRepository $repository, ?AdapterInterface $cache = null): GrantTransfer
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getReference')->willReturnCallback(fn(string $class, int $id): User => $this->user($id));
        $entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persisted[] = $entity;
            });

        $configRepository = $this->createStub(TrustContextConfigRepository::class);
        $configRepository->method('findByContext')->willReturn(null);
        $configStore = new ConfigStore($configRepository, $this->createStub(EntityManagerInterface::class));

        $scoreProvider = new ScoreProvider(
            [],
            [],
            new ContextRegistry([]),
            new ActionRegistry([]),
            $configStore,
            new ScoreCalculator(new NullLogger()),
            $repository,
            $cache ?? new ArrayAdapter(),
            new NullLogger(),
        );

        return new GrantTransfer($repository, $entityManager, $scoreProvider);
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
