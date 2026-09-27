<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit\Internal;

use DateTimeImmutable;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\TrustAction;
use Module\Trust\Tests\Stub\ActionSource;
use Module\Trust\Tests\Stub\ContextDescriber;
use Module\Trust\Tests\Stub\ExplodingCache;
use Module\Trust\Tests\Stub\ScoreProviders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ScoreProviderTest extends TestCase
{
    use ScoreProviders;

    private const string CONTEXT = ContextDescriber::CONTEXT;
    private const int MEMBER_ID = 7;

    public function testAnUnchangedRevisionDoesNotRecompute(): void
    {
        // Arrange
        $source = $this->source();
        $provider = $this->scoreProvider([$source], cache: new ArrayAdapter());

        // Act
        $provider->getMap(self::CONTEXT);
        $provider->reset();
        $provider->getMap(self::CONTEXT);

        // Assert
        self::assertSame(1, $source->replays);
    }

    public function testAChangedRevisionRecomputes(): void
    {
        // Arrange
        $source = $this->source();
        $provider = $this->scoreProvider([$source], cache: new ArrayAdapter());

        // Act
        $provider->getMap(self::CONTEXT);
        $provider->reset();
        $source->revision = 'stub-2';
        $provider->getMap(self::CONTEXT);

        // Assert
        self::assertSame(2, $source->replays);
    }

    public function testInvalidatingForcesTheNextReadToRecompute(): void
    {
        // Arrange
        $source = $this->source();
        $provider = $this->scoreProvider([$source], cache: new ArrayAdapter());

        // Act
        $provider->getMap(self::CONTEXT);
        $provider->invalidate(self::CONTEXT);
        $provider->getMap(self::CONTEXT);

        // Assert
        self::assertSame(2, $source->replays);
    }

    public function testAnUnreachableCacheStillProducesScores(): void
    {
        // Arrange
        $provider = $this->scoreProvider([$this->source()], cache: new ExplodingCache());

        // Act
        $map = $provider->getMap(self::CONTEXT);

        // Assert
        self::assertSame([self::MEMBER_ID => ActionSource::POINTS], $map);
    }

    public function testAnUndeclaredActionScoresNothingAndIsReported(): void
    {
        // Arrange
        $source = $this->source();
        $source->actions[] = new TrustAction(self::MEMBER_ID, 'never_declared', new DateTimeImmutable('2026-01-01'));
        $provider = $this->scoreProvider([$source]);

        // Act
        $map = $provider->getMap(self::CONTEXT);
        $undeclared = $provider->findUndeclaredActions(self::CONTEXT);

        // Assert
        self::assertSame([self::MEMBER_ID => ActionSource::POINTS], $map);
        self::assertSame(['never_declared'], $undeclared);
    }

    public function testAQuantityCapBoundsWhatAnActionCanEarn(): void
    {
        // Arrange
        $source = $this->source(quantity: 40);
        $source->descriptors = [new ActionDescriptor(ActionSource::HANDOVER, 'label', ActionSource::POINTS, 24)];
        $provider = $this->scoreProvider([$source]);

        // Act
        $map = $provider->getMap(self::CONTEXT);

        // Assert
        self::assertSame([self::MEMBER_ID => 24 * ActionSource::POINTS], $map);
    }

    public function testAnUndescribedContextHasNoScores(): void
    {
        // Arrange
        $source = $this->source();
        $provider = $this->scoreProvider([$source]);

        // Act
        $map = $provider->getMap('not-described');

        // Assert
        self::assertSame([], $map);
        self::assertSame(0, $source->replays);
    }

    private function source(int $quantity = 1): ActionSource
    {
        $source = new ActionSource();
        $source->actions = [new TrustAction(self::MEMBER_ID, ActionSource::HANDOVER, new DateTimeImmutable('2026-01-01'), $quantity)];

        return $source;
    }
}
