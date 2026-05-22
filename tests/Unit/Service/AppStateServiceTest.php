<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\AppState;
use App\Repository\AppStateRepository;
use App\Service\AppStateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

#[AllowMockObjectsWithoutExpectations]
class AppStateServiceTest extends TestCase
{
    private AppStateRepository&MockObject $repository;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AppStateRepository::class);
        $this->cache = new ArrayAdapter();
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
    ): AppStateService {
        return new AppStateService(
            $this->repository,
            $em ?? $this->createStub(EntityManagerInterface::class),
            $cache ?? $this->cache,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testGetReturnsNullWhenNoRowExists(): void
    {
        // Arrange
        $this->repository->method('findByKey')->willReturn(null);

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertNull($result);
    }

    public function testGetReturnsValueWhenRowExists(): void
    {
        // Arrange
        $entry = new AppState('some_key', 'stored_value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturn($entry);

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertSame('stored_value', $result);
    }

    public function testGetReadsThroughCacheOnFirstCallAndReusesItOnSecondCall(): void
    {
        // Arrange
        $entry = new AppState('cached_key', 'cached_value', new DateTimeImmutable());
        $this->repository->expects($this->once())->method('findByKey')->with('cached_key')->willReturn($entry);
        $service = $this->makeService();

        // Act
        $first = $service->get('cached_key');
        $second = $service->get('cached_key');

        // Assert
        static::assertSame('cached_value', $first);
        static::assertSame('cached_value', $second);
    }

    public function testGetReturnsNullForUnknownKeyAndCachesTheMiss(): void
    {
        // Arrange
        $this->repository->expects($this->once())->method('findByKey')->with('missing')->willReturn(null);
        $service = $this->makeService();

        // Act
        $first = $service->get('missing');
        $second = $service->get('missing');

        // Assert
        static::assertNull($first);
        static::assertNull($second);
    }

    public function testSetInvalidatesTheCache(): void
    {
        // Arrange
        $entry = new AppState('mutated_key', 'old_value', new DateTimeImmutable());
        $updated = new AppState('mutated_key', 'new_value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturnOnConsecutiveCalls($entry, $entry, $updated);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');
        $service = $this->makeService($em);

        // Act
        $primed = $service->get('mutated_key');
        $service->set('mutated_key', 'new_value');
        $afterSet = $service->get('mutated_key');

        // Assert
        static::assertSame('old_value', $primed);
        static::assertSame('new_value', $afterSet);
    }

    public function testRemoveInvalidatesTheCache(): void
    {
        // Arrange
        $entry = new AppState('rm_key', 'value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturnOnConsecutiveCalls($entry, $entry, null);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->method('flush');
        $service = $this->makeService($em);

        // Act
        $primed = $service->get('rm_key');
        $service->remove('rm_key');
        $afterRemove = $service->get('rm_key');

        // Assert
        static::assertSame('value', $primed);
        static::assertNull($afterRemove);
    }

    public function testSetCreatesNewRowWhenKeyDoesNotExist(): void
    {
        // Arrange
        $this->repository->method('findByKey')->willReturn(null);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(
                static fn(AppState $state): bool => (
                    $state->getKeyName() === 'new_key'
                    && $state->getValue() === 'new_value'
                ),
            ));
        $em->expects($this->once())->method('flush');

        // Act
        $this->makeService($em)->set('new_key', 'new_value');
    }

    public function testSetUpdatesExistingRowWhenKeyExists(): void
    {
        // Arrange
        $existing = new AppState('existing_key', 'old_value', new DateTimeImmutable('2026-01-01'));
        $this->repository->method('findByKey')->willReturn($existing);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        // Act
        $this->makeService($em)->set('existing_key', 'updated_value');

        // Assert: value was mutated on the existing entity
        static::assertSame('updated_value', $existing->getValue());
    }

    public function testRemoveDeletesExistingRow(): void
    {
        // Arrange
        $existing = new AppState('to_remove', 'value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturn($existing);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($existing);
        $em->expects($this->once())->method('flush');

        // Act
        $this->makeService($em)->remove('to_remove');
    }

    public function testRemoveIsNoOpWhenKeyMissing(): void
    {
        // Arrange
        $this->repository->method('findByKey')->willReturn(null);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        // Act
        $this->makeService($em)->remove('missing');
    }

    public function testGetFallsBackToRepositoryWhenCacheThrows(): void
    {
        // Arrange
        $entry = new AppState('fallback_key', 'fallback_value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturn($entry);

        /** @var CacheInterface&Stub $cache */
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willThrowException(new RuntimeException('valkey down'));

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = $this->makeService(cache: $cache, logger: $logger);

        // Act
        $result = $service->get('fallback_key');

        // Assert
        static::assertSame('fallback_value', $result);
    }

    public function testCacheBackendFailureIsLoggedAtMostOnce(): void
    {
        // Arrange
        $entry = new AppState('flap_key', 'value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturn($entry);

        /** @var CacheInterface&Stub $cache */
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willThrowException(new RuntimeException('valkey down'));

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = $this->makeService(cache: $cache, logger: $logger);

        // Act
        $service->get('flap_key');
        $service->get('flap_key');
        $service->get('flap_key');
    }
}
