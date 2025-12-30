<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\ConfigService;
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

        $this->assertCount(4, $functions);

        $functionNames = array_map(fn ($f) => $f->getName(), $functions);
        $this->assertContains('is_show_frontpage', $functionNames);
        $this->assertContains('get_theme_colors', $functionNames);
        $this->assertContains('get_date_format', $functionNames);
        $this->assertContains('get_date_format_flatpickr', $functionNames);
    }

    public function testIsShowFrontpageDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('isShowFrontpage')->willReturn(true);

        $functions = $this->subject->getFunctions();
        $isShowFrontpage = $this->findFunction($functions, 'is_show_frontpage');

        $this->assertTrue($isShowFrontpage->getCallable()());
    }

    public function testGetThemeColorsDelegatesToConfigService(): void
    {
        $expectedColors = ['primary' => '#ff0000', 'link' => '#0000ff'];
        $this->configServiceStub->method('getThemeColors')->willReturn($expectedColors);

        $functions = $this->subject->getFunctions();
        $getThemeColors = $this->findFunction($functions, 'get_theme_colors');

        $this->assertSame($expectedColors, $getThemeColors->getCallable()());
    }

    public function testGetDateFormatDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormat')->willReturn('d.m.Y H:i');

        $functions = $this->subject->getFunctions();
        $getDateFormat = $this->findFunction($functions, 'get_date_format');

        $this->assertSame('d.m.Y H:i', $getDateFormat->getCallable()());
    }

    public function testGetDateFormatFlatpickrDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormatFlatpickr')->willReturn('d.m.Y H:i');

        $functions = $this->subject->getFunctions();
        $getDateFormat = $this->findFunction($functions, 'get_date_format_flatpickr');

        $this->assertSame('d.m.Y H:i', $getDateFormat->getCallable()());
    }

    private function findFunction(array $functions, string $name): ?\Twig\TwigFunction
    {
        foreach ($functions as $function) {
            if ($function->getName() === $name) {
                return $function;
            }
        }

        return null;
    }
}
