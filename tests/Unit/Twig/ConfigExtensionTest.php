<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Config\ConfigService;
use App\Twig\ConfigExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConfigExtensionTest extends TestCase
{
    private Stub&ConfigService $configServiceStub;
    private ConfigExtension $subject;

    protected function setUp(): void
    {
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->subject = new ConfigExtension($this->configServiceStub);
    }

    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->subject->getFunctions();

        static::assertCount(5, $functions);

        $functionNames = array_map(static fn($f) => $f->getName(), $functions);
        static::assertContains('is_show_frontpage', $functionNames);
        static::assertContains('get_theme_colors', $functionNames);
        static::assertContains('get_date_format', $functionNames);
        static::assertContains('get_date_format_flatpickr', $functionNames);
        static::assertContains('get_footer_column_title', $functionNames);
    }

    public function testIsShowFrontpageDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('isShowFrontpage')->willReturn(true);

        $functions = $this->subject->getFunctions();
        $isShowFrontpage = $this->findFunction($functions, 'is_show_frontpage');

        static::assertTrue($isShowFrontpage->getCallable()());
    }

    public function testGetThemeColorsDelegatesToConfigService(): void
    {
        $expectedColors = ['primary' => '#ff0000', 'link' => '#0000ff'];
        $this->configServiceStub->method('getThemeColors')->willReturn($expectedColors);

        $functions = $this->subject->getFunctions();
        $getThemeColors = $this->findFunction($functions, 'get_theme_colors');

        static::assertSame($expectedColors, $getThemeColors->getCallable()());
    }

    public function testGetDateFormatDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormat')->willReturn('d.m.Y H:i');

        $functions = $this->subject->getFunctions();
        $getDateFormat = $this->findFunction($functions, 'get_date_format');

        static::assertSame('d.m.Y H:i', $getDateFormat->getCallable()());
    }

    public function testGetDateFormatFlatpickrDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormatFlatpickr')->willReturn('d.m.Y H:i');

        $functions = $this->subject->getFunctions();
        $getDateFormat = $this->findFunction($functions, 'get_date_format_flatpickr');

        static::assertSame('d.m.Y H:i', $getDateFormat->getCallable()());
    }

    private function findFunction(array $functions, string $name): ?\Twig\TwigFunction
    {
        foreach ($functions as $function) {
            if ($function->getName() !== $name) {
                continue;
            }

            return $function;
        }

        return null;
    }
}
