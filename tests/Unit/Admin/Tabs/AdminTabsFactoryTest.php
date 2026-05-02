<?php declare(strict_types=1);

namespace App\Tests\Unit\Admin\Tabs;

use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabsFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \App\Admin\Tabs\AdminTabsFactory
 */
#[AllowMockObjectsWithoutExpectations]
final class AdminTabsFactoryTest extends TestCase
{
    public function testTabTranslatesLabelGeneratesUrlAndForwardsIconAndIsActive(): void
    {
        // Arrange
        $router = $this->createStub(RouterInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);

        $translator
            ->method('trans')
            ->willReturnCallback(static function (string $key): string {
                if ($key === 'admin_logs.tab_cron') {
                    return 'Cron';
                }

                return $key;
            });
        $router
            ->method('generate')
            ->willReturnCallback(static function (string $name, array $params = []): string {
                if ($name === 'app_admin_cron_log' && $params === ['foo' => 'bar']) {
                    return '/admin/logs/cron?foo=bar';
                }

                return '/dummy';
            });

        $factory = new AdminTabsFactory($router, $translator);

        // Act
        $tab = $factory->tab(
            labelKey: 'admin_logs.tab_cron',
            route: 'app_admin_cron_log',
            routeParams: ['foo' => 'bar'],
            icon: 'clock',
            isActive: true,
        );

        // Assert
        static::assertInstanceOf(AdminTab::class, $tab);
        static::assertSame('Cron', $tab->label);
        static::assertSame('/admin/logs/cron?foo=bar', $tab->target);
        static::assertSame('clock', $tab->icon);
        static::assertTrue($tab->isActive);
    }
}
