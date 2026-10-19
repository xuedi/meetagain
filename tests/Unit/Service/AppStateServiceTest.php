<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\AppState;
use App\Repository\AppStateRepository;
use App\Service\AppStateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        (CacheInterface&CacheItemPoolInterface)|null $cache = null,
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
        $this->repository->method('findValue')->willReturn(null);

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertNull($result);
    }

    public function testGetReturnsValueWhenRowExists(): void
    {
        // Arrange
        $this->repository->method('findValue')->willReturn('stored_value');

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertSame('stored_value', $result);
    }

    public function testGetReadsThroughCacheOnFirstCallAndReusesItOnSecondCall(): void
    {
        // Arrange
        $this->repository->expects($this->once())->method('findValue')->with('cached_key')->willReturn('cached_value');
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
        $this->repository->expects($this->once())->method('findValue')->with('missing')->willReturn(null);
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
        $this->repository->method('findValue')->willReturnOnConsecutiveCalls('old_value', 'new_value');
        $service = $this->makeService();

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
        $this->repository->method('findValue')->willReturnOnConsecutiveCalls('value', null);
        $this->repository->method('findByKey')->willReturn($entry);

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

    public function testSetWritesThroughOneUpsert(): void
    {
        // Arrange
        $this->repository->expects($this->never())->method('findByKey');
        $this->repository->expects($this->once())->method('upsert')->with('new_key', 'new_value', static::isInstanceOf(DateTimeImmutable::class));

        // Act
        $this->makeService()->set('new_key', 'new_value');
    }

    public function testSetIfAbsentReturnsTheValueAlreadyStored(): void
    {
        // Arrange
        $this->repository->expects($this->once())->method('insertIfAbsent')->with('race_key', 'loser_value');
        $this->repository->expects($this->once())->method('findValue')->with('race_key')->willReturn('winner_value');

        // Act
        $result = $this->makeService()->setIfAbsent('race_key', 'loser_value');

        // Assert
        static::assertSame('winner_value', $result);
    }

    public function testAMissIsCachedForAMinuteOnly(): void
    {
        // Arrange
        $this->repository->method('findValue')->willReturn(null);
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(60);
        $cache = $this->createStubForIntersectionOfInterfaces([CacheInterface::class, CacheItemPoolInterface::class]);
        $cache->method('get')->willReturnCallback(static fn(string $key, callable $callback): mixed => $callback($item));

        // Act
        $this->makeService(cache: $cache)->get('missing_key');
    }

    public function testAStoredValueIsCachedForFiveMinutes(): void
    {
        // Arrange
        $this->repository->method('findValue')->willReturn('present');
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(300);
        $cache = $this->createStubForIntersectionOfInterfaces([CacheInterface::class, CacheItemPoolInterface::class]);
        $cache->method('get')->willReturnCallback(static fn(string $key, callable $callback): mixed => $callback($item));

        // Act
        $this->makeService(cache: $cache)->get('present_key');
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
        $this->repository->method('findValue')->willReturn('fallback_value');

        $cache = $this->makeFailingCache();

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
        $this->repository->method('findValue')->willReturn('value');

        $cache = $this->makeFailingCache();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = $this->makeService(cache: $cache, logger: $logger);

        // Act
        $service->get('flap_key');
        $service->get('flap_key');
        $service->get('flap_key');
    }

    public function testGetManyReadsEveryMissingKeyInOneRepositoryCall(): void
    {
        // Arrange
        $this->repository->expects($this->never())->method('findValue');
        $this->repository->expects($this->once())->method('findValuesByKeys')->with(['a_key', 'b_key'])->willReturn(['a_key' => 'a_value']);
        $service = $this->makeService();

        // Act
        $result = $service->getMany(['a_key', 'b_key']);

        // Assert
        static::assertSame(['a_key' => 'a_value', 'b_key' => null], $result);
    }

    public function testGetManyServesRepeatedCallsFromTheMemo(): void
    {
        // Arrange
        $this->repository->expects($this->once())->method('findValuesByKeys')->willReturn(['memo_key' => 'memo_value']);
        $service = $this->makeService();

        // Act
        $first = $service->getMany(['memo_key']);
        $second = $service->getMany(['memo_key']);
        $single = $service->get('memo_key');

        // Assert
        static::assertSame(['memo_key' => 'memo_value'], $first);
        static::assertSame($first, $second);
        static::assertSame('memo_value', $single);
    }

    public function testGetManyOnlyResolvesKeysThatAreNotMemoizedYet(): void
    {
        // Arrange
        $this->repository->method('findValue')->willReturn('primed_value');
        $this->repository->expects($this->once())->method('findValuesByKeys')->with(['fresh'])->willReturn(['fresh' => 'fresh_value']);
        $service = $this->makeService();

        // Act
        $service->get('primed');
        $result = $service->getMany(['primed', 'fresh']);

        // Assert
        static::assertSame(['primed' => 'primed_value', 'fresh' => 'fresh_value'], $result);
    }

    public function testGetManyFallsBackToRepositoryWhenCacheThrows(): void
    {
        // Arrange
        $this->repository->expects($this->once())->method('findValuesByKeys')->with(['down_key'])->willReturn(['down_key' => 'down_value']);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = $this->makeService(cache: $this->makeFailingCache(), logger: $logger);

        // Act
        $result = $service->getMany(['down_key']);

        // Assert
        static::assertSame(['down_key' => 'down_value'], $result);
    }

    public function testSetDropsTheMemoizedValue(): void
    {
        // Arrange
        $this->repository->method('findValuesByKeys')->willReturn(['memo_invalidated' => 'old_value']);
        $this->repository->method('findValue')->willReturn('new_value');
        $service = $this->makeService();

        // Act
        $primed = $service->getMany(['memo_invalidated'])['memo_invalidated'];
        $service->set('memo_invalidated', 'new_value');
        $afterSet = $service->get('memo_invalidated');

        // Assert
        static::assertSame('old_value', $primed);
        static::assertSame('new_value', $afterSet);
    }

    public function testResetDropsTheMemoSoTheNextReadResolvesAgain(): void
    {
        // Arrange
        $this->repository->method('findValuesByKeys')->willReturnOnConsecutiveCalls(['reset_key' => 'first'], ['reset_key' => 'second']);
        $service = $this->makeService();

        // Act
        $primed = $service->getMany(['reset_key'])['reset_key'];
        $this->cache->deleteItem('app_state.reset_key');
        $stillMemoized = $service->getMany(['reset_key'])['reset_key'];
        $service->reset();
        $afterReset = $service->getMany(['reset_key'])['reset_key'];

        // Assert
        static::assertSame('first', $primed);
        static::assertSame('first', $stillMemoized);
        static::assertSame('second', $afterReset);
    }

    private function makeFailingCache(): CacheInterface&CacheItemPoolInterface
    {
        $cache = $this->createStubForIntersectionOfInterfaces([CacheInterface::class, CacheItemPoolInterface::class]);
        $cache->method('get')->willThrowException(new RuntimeException('valkey down'));
        $cache->method('getItems')->willThrowException(new RuntimeException('valkey down'));

        return $cache;
    }
}
