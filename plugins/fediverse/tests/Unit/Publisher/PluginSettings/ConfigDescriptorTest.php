<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Publisher\PluginSettings;

use App\Admin\Top\Actions\AdminTopActionForm;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Controller\AdminController;
use Plugin\Fediverse\Publisher\PluginSettings\ConfigDescriptor;
use Plugin\Fediverse\Repository\AccountRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigDescriptorTest extends TestCase
{
    public function testStopIsOfferedWhileAnAccountIsLive(): void
    {
        // Act
        $actions = $this->descriptor(liveAccounts: 1)->getTopActions();

        // Assert
        static::assertCount(1, $actions);
        $stop = $actions[0];
        static::assertInstanceOf(AdminTopActionForm::class, $stop);
        static::assertSame('/en/admin/plugin/fediverse/stop', $stop->target);
        static::assertSame(AdminController::STOP_TOKEN, $stop->csrfTokenId);
        static::assertSame('fediverse_config.stop_button', $stop->label);
        static::assertSame('fediverse_config.stop_confirm', $stop->confirm);
    }

    public function testStopIsGoneOnceNoAccountIsLive(): void
    {
        // Act
        $actions = $this->descriptor(liveAccounts: 0)->getTopActions();

        // Assert
        static::assertSame([], $actions);
    }

    private function descriptor(int $liveAccounts): ConfigDescriptor
    {
        $accounts = $this->createStub(AccountRepository::class);
        $accounts->method('countLive')->willReturn($liveAccounts);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn(string $route): string => $route === 'app_admin_plugin_fediverse_stop'
            ? '/en/admin/plugin/fediverse/stop'
            : '/');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ConfigDescriptor($accounts, $urlGenerator, $translator);
    }
}
