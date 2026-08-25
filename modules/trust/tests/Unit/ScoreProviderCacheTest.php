<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Contract\ContextDescriptor;
use Module\Trust\Contract\TrustAction;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Module\Trust\Internal\ScoreCalculator;
use Module\Trust\Internal\ScoreProvider;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

class ScoreProviderCacheTest extends TestCase
{
    public function testAnUnchangedRevisionDoesNotRecompute(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $provider->getMap('ctx');
        $provider->reset();
        $provider->getMap('ctx');

        // Assert
        self::assertSame(1, $source->replays);
    }

    public function testAChangedRevisionRecomputes(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $provider->getMap('ctx');
        $provider->reset();
        $source->revision = 'rev-2';
        $provider->getMap('ctx');

        // Assert
        self::assertSame(2, $source->replays);
    }

    public function testInvalidatingForcesTheNextReadToRecompute(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $provider->getMap('ctx');
        $provider->invalidate('ctx');
        $provider->getMap('ctx');

        // Assert
        self::assertSame(2, $source->replays);
    }

    public function testAnUnreachableCacheStillProducesScores(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $provider = $this->provider($source, new ExplodingCache());

        // Act
        $map = $provider->getMap('ctx');

        // Assert
        self::assertSame([7 => 5], $map);
    }

    public function testAnUndeclaredActionScoresNothingAndIsReported(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $source->extraAction = 'never_declared';
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $map = $provider->getMap('ctx');
        $undeclared = $provider->findUndeclaredActions('ctx');

        // Assert
        self::assertSame([7 => 5], $map);
        self::assertSame(['never_declared'], $undeclared);
    }

    public function testAQuantityCapBoundsWhatAnActionCanEarn(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $source->quantity = 40;
        $source->cap = 24;
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $map = $provider->getMap('ctx');

        // Assert
        self::assertSame([7 => 24 * 5], $map);
    }

    public function testAnUndescribedContextHasNoScores(): void
    {
        // Arrange
        $source = new CountingActionSource('rev-1');
        $provider = $this->provider($source, new ArrayAdapter());

        // Act
        $map = $provider->getMap('not-described');

        // Assert
        self::assertSame([], $map);
        self::assertSame(0, $source->replays);
    }

    private function provider(CountingActionSource $source, CacheItemPoolInterface $cache): ScoreProvider
    {
        $configRepository = $this->createStub(TrustContextConfigRepository::class);
        $configRepository->method('findByContext')->willReturn(null);

        $grantRepository = $this->createStub(TrustGrantRepository::class);
        $grantRepository->method('findEdges')->willReturn([]);
        $grantRepository->method('findRevision')->willReturn(null);

        return new ScoreProvider(
            [$source],
            [],
            new ContextRegistry([new SingleContextDescriber()]),
            new ActionRegistry([$source]),
            new ConfigStore($configRepository, $this->createStub(EntityManagerInterface::class)),
            new ScoreCalculator(new NullLogger()),
            $grantRepository,
            $cache,
            new NullLogger(),
        );
    }
}

final class SingleContextDescriber implements ContextDescriberInterface
{
    #[Override]
    public function describe(string $context): ?ContextDescriptor
    {
        return $context === 'ctx' ? new ContextDescriptor('ctx', 'Context') : null;
    }

    #[Override]
    public function describeAll(): iterable
    {
        yield new ContextDescriptor('ctx', 'Context');
    }
}

final class CountingActionSource implements ActionSourceInterface
{
    public int $replays = 0;

    public int $quantity = 1;

    public ?int $cap = null;

    public ?string $extraAction = null;

    public function __construct(
        public string $revision,
    ) {}

    #[Override]
    public function describeActions(string $context): iterable
    {
        yield new ActionDescriptor('handover', 'label', 5, $this->cap);
    }

    #[Override]
    public function replay(string $context): iterable
    {
        $this->replays++;

        $actions = [new TrustAction(7, 'handover', new DateTimeImmutable('2026-01-01'), $this->quantity)];
        if ($this->extraAction !== null) {
            $actions[] = new TrustAction(7, $this->extraAction, new DateTimeImmutable('2026-01-01'));
        }

        return $actions;
    }

    #[Override]
    public function getRevision(string $context): ?string
    {
        return $this->revision;
    }
}

final class ExplodingCache extends ArrayAdapter
{
    #[Override]
    public function getItem(mixed $key): CacheItem
    {
        throw new CacheUnreachable('Valkey is down.');
    }

    #[Override]
    public function deleteItem(mixed $key): bool
    {
        throw new CacheUnreachable('Valkey is down.');
    }
}

final class CacheUnreachable extends RuntimeException {}
