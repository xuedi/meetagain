<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Filter\Plugin\PluginListFilterInterface;
use App\Repository\PluginActivationRepository;
use App\Service\Config\PluginService;
use Doctrine\DBAL\Exception\TableNotFoundException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

class PluginServiceTest extends TestCase
{
    public function testAPluginIsActiveOnlyWhenItsRowSwitchesItOn(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin(), 'films' => $this->plugin()], ['books' => true, 'films' => false]);

        // Act
        $active = $subject->getGloballyActiveList();

        // Assert
        static::assertSame(['books', PluginService::NAVIGATION_KEY], $active);
    }

    public function testAPluginWithoutARowIsOff(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin()], []);

        // Act
        $active = $subject->getGloballyActiveList();

        // Assert
        static::assertSame([PluginService::NAVIGATION_KEY], $active);
    }

    public function testASystemPluginIsAlwaysActive(): void
    {
        // Arrange
        $subject = $this->service(['pinned' => $this->plugin(system: true)], ['pinned' => false]);

        // Act
        $active = $subject->getGloballyActiveList();

        // Assert
        static::assertContains('pinned', $active);
    }

    public function testAnUnreadableTableTreatsEveryInstalledPluginAsActive(): void
    {
        // Arrange
        $repository = $this->createStub(PluginActivationRepository::class);
        $repository->method('stateByKey')->willThrowException($this->createStub(TableNotFoundException::class));
        $subject = new PluginService(['books' => $this->plugin()], $repository, new ArrayAdapter(), new ArrayAdapter(), new ArrayAdapter(), new MockClock());

        // Act
        $active = $subject->getGloballyActiveList();

        // Assert
        static::assertSame(['books', PluginService::NAVIGATION_KEY], $active);
    }

    public function testTheGlobalListIgnoresTheGroupFilters(): void
    {
        // Arrange
        $filter = $this->createStub(PluginListFilterInterface::class);
        $filter->method('filterActivePlugins')->willReturn([]);
        $subject = $this->service(['books' => $this->plugin()], ['books' => true], [$filter]);

        // Act
        $global = $subject->getGloballyActiveList();
        $active = $subject->getActiveList();

        // Assert
        static::assertContains('books', $global);
        static::assertNotContains('books', $active);
        static::assertContains(PluginService::NAVIGATION_KEY, $active);
    }

    public function testAFilterReturningNullLeavesTheGlobalList(): void
    {
        // Arrange
        $filter = $this->createStub(PluginListFilterInterface::class);
        $filter->method('filterActivePlugins')->willReturn(null);
        $subject = $this->service(['books' => $this->plugin()], ['books' => true], [$filter]);

        // Act
        $active = $subject->getActiveList();

        // Assert
        static::assertContains('books', $active);
    }

    public function testAGloballySwitchedOffPluginIsMissingFromTheGroupList(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin(), 'films' => $this->plugin()], ['books' => true]);

        // Act
        $keys = array_column($subject->getActivatableByGroupList(), 'key');

        // Assert
        static::assertSame(['books'], $keys);
    }

    public function testAPluginThatIsNotGroupActivatableIsMissingFromTheGroupList(): void
    {
        // Arrange
        $subject = $this->service(['wishlist' => $this->plugin(groupActivatable: false)], ['wishlist' => true]);

        // Act
        $list = $subject->getActivatableByGroupList();

        // Assert
        static::assertSame([], $list);
    }

    public function testTheAdminListCarriesTheManifestAndTheState(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin(requires: ['films']), 'films' => $this->plugin()], ['films' => true]);

        // Act
        $list = $subject->getAdminList();

        // Assert
        static::assertSame(
            [
                [
                    'key' => 'books',
                    'name' => 'Name',
                    'version' => '1.0',
                    'description' => 'Description',
                    'system' => false,
                    'enabled' => false,
                    'requires' => ['films'],
                ],
                [
                    'key' => 'films',
                    'name' => 'Name',
                    'version' => '1.0',
                    'description' => 'Description',
                    'system' => false,
                    'enabled' => true,
                    'requires' => [],
                ],
            ],
            $list,
        );
    }

    public function testATogglePersistsTheStateAndTheNextReadSeesIt(): void
    {
        // Arrange
        $saved = [];
        $repository = $this->createStub(PluginActivationRepository::class);
        $repository
            ->method('stateByKey')
            ->willReturnCallback(static function () use (&$saved): array {
                return $saved;
            });
        $repository
            ->method('save')
            ->willReturnCallback(static function (string $key, bool $enabled) use (&$saved): void {
                $saved[$key] = $enabled;
            });
        $subject = new PluginService(['books' => $this->plugin()], $repository, new ArrayAdapter(), new ArrayAdapter(), new ArrayAdapter(), new MockClock());
        $subject->getGloballyActiveList();

        // Act
        $subject->toggle('books', true);

        // Assert
        static::assertTrue($subject->isEnabled('books'));
    }

    public function testAToggleAsksTheRunningWorkersToRestart(): void
    {
        // Arrange
        $signal = new ArrayAdapter();
        $repository = $this->createStub(PluginActivationRepository::class);
        $repository->method('stateByKey')->willReturn([]);
        $subject = new PluginService(
            ['books' => $this->plugin()],
            $repository,
            new ArrayAdapter(),
            new ArrayAdapter(),
            $signal,
            new MockClock('2026-10-18 12:00:00 UTC'),
        );

        // Act
        $subject->toggle('books', true);

        // Assert
        $restart = $signal->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        static::assertTrue($restart->isHit());
        static::assertEqualsWithDelta(1_792_324_800.0, $restart->get(), 0.001);
    }

    public function testSwitchingOnRefusesWhileARequirementIsOff(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin(requires: ['films']), 'films' => $this->plugin()], []);

        // Assert
        $this->expectException(LogicException::class);

        // Act
        $subject->toggle('books', true);
    }

    public function testSwitchingOffRefusesWhileADependentIsOn(): void
    {
        // Arrange
        $subject = $this->service(['books' => $this->plugin(requires: ['films']), 'films' => $this->plugin()], ['books' => true, 'films' => true]);

        // Assert
        $this->expectException(LogicException::class);

        // Act
        $subject->toggle('films', false);
    }

    public function testASystemPluginCannotBeSwitched(): void
    {
        // Arrange
        $subject = $this->service(['pinned' => $this->plugin(system: true)], []);

        // Assert
        $this->expectException(LogicException::class);

        // Act
        $subject->toggle('pinned', false);
    }

    /**
     * @param array<string, array{name: string, version: string, description: string, icon: string, group_activatable: bool, system: bool, requires: list<string>}> $plugins
     * @param array<string, bool> $state
     * @param list<PluginListFilterInterface> $filters
     */
    private function service(array $plugins, array $state, array $filters = []): PluginService
    {
        $repository = $this->createStub(PluginActivationRepository::class);
        $repository->method('stateByKey')->willReturn($state);

        return new PluginService($plugins, $repository, new ArrayAdapter(), new ArrayAdapter(), new ArrayAdapter(), new MockClock(), $filters);
    }

    /**
     * @param list<string> $requires
     *
     * @return array{name: string, version: string, description: string, icon: string, group_activatable: bool, system: bool, requires: list<string>}
     */
    private function plugin(bool $system = false, bool $groupActivatable = true, array $requires = []): array
    {
        return [
            'name' => 'Name',
            'version' => '1.0',
            'description' => 'Description',
            'icon' => 'plug',
            'group_activatable' => $groupActivatable,
            'system' => $system,
            'requires' => $requires,
        ];
    }
}
