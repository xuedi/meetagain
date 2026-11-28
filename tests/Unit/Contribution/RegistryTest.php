<?php declare(strict_types=1);

namespace Tests\Unit\Contribution;

use App\Contribution\Registry;
use App\Contribution\TargetProviderInterface;
use App\Entity\User;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testAnUnknownTypeIsNotRegistered(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location')], $this->plugins());

        // Act & Assert
        self::assertTrue($registry->has('location'));
        self::assertFalse($registry->has('event'));
    }

    public function testMayTouchAsksTheProvider(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location', touchable: [9])], $this->plugins());

        // Act & Assert
        self::assertTrue($registry->mayTouch('location', new User(), 9));
        self::assertFalse($registry->mayTouch('location', new User(), 1));
    }

    public function testMayTouchRefusesAnUnknownType(): void
    {
        // Arrange
        $registry = new Registry([], $this->plugins());

        // Act
        $verdict = $registry->mayTouch('location', new User(), 1);

        // Assert
        self::assertFalse($verdict);
    }

    public function testEveryRegisteredProviderIsListed(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location'), $this->provider('event')], $this->plugins());

        // Act
        $types = array_map(static fn(TargetProviderInterface $p): string => $p->getType(), $registry->all());

        // Assert
        self::assertSame(['location', 'event'], $types);
    }

    public function testAProviderFromAnInactivePluginIsClosed(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('glossary', touchable: [1], pluginKey: 'glossary')], $this->plugins([]));

        // Act & Assert
        self::assertSame([], $registry->all());
        self::assertFalse($registry->has('glossary'));
        self::assertFalse($registry->mayTouch('glossary', new User(), 1), 'an inactive plugin closes the form');
    }

    public function testAProviderFromAnActivePluginIsOpen(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('glossary', touchable: [1], pluginKey: 'glossary')], $this->plugins(['glossary']));

        // Act & Assert
        self::assertTrue($registry->has('glossary'));
        self::assertTrue($registry->mayTouch('glossary', new User(), 1));
    }

    public function testACoreProviderIsOpenWithoutAnyPluginBeingActive(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('location')], $this->plugins([]));

        // Act & Assert
        self::assertTrue($registry->has('location'));
    }

    /**
     * @param list<int> $touchable
     */
    private function provider(string $type, array $touchable = [], string $pluginKey = ''): TargetProviderInterface
    {
        return new class($type, $touchable, $pluginKey) implements TargetProviderInterface {
            /**
             * @param list<int> $touchable
             */
            public function __construct(
                private readonly string $type,
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
}
