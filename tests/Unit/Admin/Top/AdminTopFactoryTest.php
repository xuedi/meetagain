<?php declare(strict_types=1);

namespace App\Tests\Unit\Admin\Top;

use App\Admin\Top\AdminTopFactory;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \App\Admin\Top\AdminTopFactory
 */
#[AllowMockObjectsWithoutExpectations]
final class AdminTopFactoryTest extends TestCase
{
    private RouterInterface $router;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->translator = $this->createStub(TranslatorInterface::class);
    }

    public function testInfoTextCallsTranslatorWithKeyAndParamsAndReturnsAdminTopInfoText(): void
    {
        // Arrange
        $this->translator
            ->method('trans')
            ->willReturnCallback(static function (string $key, array $params = []): string {
                if ($key === 'menu.label' && $params === ['count' => 3]) {
                    return 'Three items';
                }

                return $key;
            });

        $factory = new AdminTopFactory($this->router, $this->translator);

        // Act
        $info = $factory->infoText('menu.label', ['count' => 3]);

        // Assert
        static::assertInstanceOf(AdminTopInfoText::class, $info);
        static::assertSame('Three items', $info->text);
    }

    public function testInfoHtmlPassesThroughTrustedHtmlUnchanged(): void
    {
        // Arrange
        $factory = new AdminTopFactory($this->router, $this->translator);
        $trusted = '<strong>42</strong> total';

        // Act
        $info = $factory->infoHtml($trusted);

        // Assert
        static::assertInstanceOf(AdminTopInfoHtml::class, $info);
        static::assertSame($trusted, $info->html);
    }

    public function testActionButtonTranslatesLabelGeneratesUrlAndForwardsIconAndVariant(): void
    {
        // Arrange
        $this->translator
            ->method('trans')
            ->willReturnCallback(static function (string $key): string {
                if ($key === 'admin_logs.back') {
                    return 'Back';
                }

                return $key;
            });
        $this->router
            ->method('generate')
            ->willReturnCallback(static function (string $name, array $params = []): string {
                if ($name === 'app_admin_cron_log' && $params === ['problemsOnly' => 1]) {
                    return '/admin/logs/cron?problemsOnly=1';
                }

                return '/dummy';
            });

        $factory = new AdminTopFactory($this->router, $this->translator);

        // Act
        $button = $factory->actionButton(
            labelKey: 'admin_logs.back',
            route: 'app_admin_cron_log',
            routeParams: ['problemsOnly' => 1],
            icon: 'arrow-left',
            variant: 'is-danger is-light',
        );

        // Assert
        static::assertInstanceOf(AdminTopActionButton::class, $button);
        static::assertSame('Back', $button->label);
        static::assertSame('/admin/logs/cron?problemsOnly=1', $button->target);
        static::assertSame('arrow-left', $button->icon);
        static::assertSame('is-danger is-light', $button->variant);
    }
}
