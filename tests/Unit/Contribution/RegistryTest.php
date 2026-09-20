<?php declare(strict_types=1);

namespace Tests\Unit\Contribution;

use App\Contribution\Entry;
use App\Contribution\Registry;
use App\Contribution\ScopeFilterInterface;
use App\Contribution\ScopeFilterService;
use App\Contribution\TargetProviderInterface;
use App\Entity\User;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testAProviderWithNoEntriesContributesNothing(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [])], new ScopeFilterService([]), $this->plugins());

        // Act
        $entries = $registry->entriesFor('location', new User());

        // Assert
        self::assertSame([], $entries);
    }

    public function testAnUnknownTypeIsNotRegistered(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [1])], new ScopeFilterService([]), $this->plugins());

        // Act & Assert
        self::assertTrue($registry->has('location'));
        self::assertFalse($registry->has('event'));
        self::assertSame([], $registry->entriesFor('event', new User()));
    }

    public function testEveryEntrySurvivesWhenNoFilterHasAnOpinion(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [1, 2, 3])], new ScopeFilterService([]), $this->plugins());

        // Act
        $ids = $this->idsOf($registry->entriesFor('location', new User()));

        // Assert
        self::assertSame([1, 2, 3], $ids);
    }

    public function testTheScopeChainNarrowsTheListing(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [1, 2, 3])], new ScopeFilterService([$this->filter([2, 3])]), $this->plugins());

        // Act
        $ids = $this->idsOf($registry->entriesFor('location', new User()));

        // Assert
        self::assertSame([2, 3], $ids);
    }

    public function testEachTypeIsNarrowedSeparately(): void
    {
        // Arrange
        $registry = new Registry(
            [$this->provider('location', [1, 2]), $this->provider('event', [1, 2])],
            new ScopeFilterService([$this->filter([1], onlyType: 'location')]),
            $this->plugins(),
        );

        // Act
        $locations = $this->idsOf($registry->entriesFor('location', new User()));
        $events = $this->idsOf($registry->entriesFor('event', new User()));

        // Assert
        self::assertSame([1], $locations);
        self::assertSame([1, 2], $events);
    }

    public function testMayTouchAsksTheProviderAndIsNotNarrowedByTheScopeChain(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [1], touchable: [9])], new ScopeFilterService([$this->filter([])]), $this->plugins());

        // Act
        $verdict = $registry->mayTouch('location', new User(), 9);

        // Assert
        self::assertTrue($verdict, 'a row reachable through another link survives a blocking scope chain');
    }

    public function testMayTouchRefusesAnUnknownType(): void
    {
        // Arrange
        $registry = new Registry([], new ScopeFilterService([]), $this->plugins());

        // Act
        $verdict = $registry->mayTouch('location', new User(), 1);

        // Assert
        self::assertFalse($verdict);
    }

    public function testEveryRegisteredProviderIsListed(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', []), $this->provider('event', [])], new ScopeFilterService([]), $this->plugins());

        // Act
        $types = array_map(static fn(TargetProviderInterface $p): string => $p->getType(), $registry->all());

        // Assert
        self::assertSame(['location', 'event'], $types);
    }

    public function testASectionFromAnInactivePluginIsNotOffered(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('glossary', [1], touchable: [1], pluginKey: 'glossary')], new ScopeFilterService([]), $this->plugins([]));

        // Act & Assert
        self::assertSame([], $registry->all());
        self::assertFalse($registry->has('glossary'));
        self::assertSame([], $registry->entriesFor('glossary', new User()));
        self::assertFalse($registry->mayTouch('glossary', new User(), 1), 'an inactive plugin closes the form as well as the listing');
    }

    public function testASectionFromAnActivePluginIsOffered(): void
    {
        // Arrange
        $registry = new Registry(
            [$this->provider('glossary', [1], touchable: [1], pluginKey: 'glossary')],
            new ScopeFilterService([]),
            $this->plugins(['glossary']),
        );

        // Act & Assert
        self::assertTrue($registry->has('glossary'));
        self::assertTrue($registry->mayTouch('glossary', new User(), 1));
    }

    public function testACoreSectionIsOfferedWithoutAnyPluginBeingActive(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', [1])], new ScopeFilterService([]), $this->plugins([]));

        // Act & Assert
        self::assertTrue($registry->has('location'));
    }

    /**
     * @param  list<Entry> $entries
     * @return list<int>
     */
    private function idsOf(array $entries): array
    {
        return array_map(static fn(Entry $entry): int => $entry->id, $entries);
    }

    /**
     * @param list<int> $ids
     * @param list<int> $touchable
     */
    private function provider(string $type, array $ids, array $touchable = [], string $pluginKey = ''): TargetProviderInterface
    {
        return new class($type, $ids, $touchable, $pluginKey) implements TargetProviderInterface {
            /**
             * @param list<int> $ids
             * @param list<int> $touchable
             */
            public function __construct(
                private readonly string $type,
                private readonly array $ids,
                private readonly array $touchable,
                private readonly string $pluginKey,
            ) {}

            public function getType(): string
            {
                return $this->type;
            }

            public function getPluginKey(): string
            {
                return $this->pluginKey;
            }

            public function getLabelKey(): string
            {
                return 'contribution.section_' . $this->type;
            }

            public function getIcon(): string
            {
                return 'fa-pen';
            }

            public function listForMember(User $user): array
            {
                return array_map(static fn(int $id): Entry => new Entry($id, 'Row ' . $id), $this->ids);
            }

            public function mayTouch(User $user, int|string $id): bool
            {
                return in_array($id, $this->touchable, true);
            }
        };
    }

    /**
     * @param list<string> $activePlugins
     */
    private function plugins(array $activePlugins = []): PluginService
    {
        $service = $this->createStub(PluginService::class);
        $service->method('getActiveList')->willReturn($activePlugins);

        return $service;
    }

    /**
     * @param list<int>|null $visible
     */
    private function filter(?array $visible, ?string $onlyType = null): ScopeFilterInterface
    {
        return new class($visible, $onlyType) implements ScopeFilterInterface {
            /**
             * @param list<int>|null $visible
             */
            public function __construct(
                private readonly ?array $visible,
                private readonly ?string $onlyType,
            ) {}

            public function getPriority(): int
            {
                return 0;
            }

            public function narrowContributableIds(string $type, array $ids, User $user): ?array
            {
                if ($this->onlyType !== null && $this->onlyType !== $type) {
                    return null;
                }

                return $this->visible;
            }
        };
    }
}
