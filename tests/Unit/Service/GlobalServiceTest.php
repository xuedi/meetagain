<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Config;
use App\Entity\ImageType;
use App\Repository\ConfigRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\DashboardService;
use App\Service\GlobalService;
use App\Service\PluginService;
use App\Service\TranslationService;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GlobalServiceTest extends TestCase
{
    private MockObject|RequestStack $requestStackMock;
    private MockObject|TranslationService $translationServiceMock;
    private MockObject|DashboardService $dashboardServiceMock;
    private MockObject|UserRepository $userRepositoryMock;
    private MockObject|PluginService $pluginServiceMock;
    private GlobalService $subject;

    protected function setUp(): void
    {
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->translationServiceMock = $this->createMock(TranslationService::class);
        $this->dashboardServiceMock = $this->createMock(DashboardService::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->pluginServiceMock = $this->createMock(PluginService::class);

        $this->subject = new GlobalService(
            requestStack: $this->requestStackMock,
            translationService: $this->translationServiceMock,
            dashboardService: $this->dashboardServiceMock,
            userRepo: $this->userRepositoryMock,
            pluginService: $this->pluginServiceMock
        );
    }

    public function testCurrentLocale(): void
    {
        $expected = 'de';

        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->method('getLocale')
            ->willReturn($expected);

        $this->requestStackMock
            ->method('getCurrentRequest')
            ->willReturn($requestMock);

        $this->assertEquals($expected, $this->subject->getCurrentLocale());
    }

    public function testCatchUnknownCurrentLocale(): void
    {
        $this->expectExceptionObject(new RuntimeException('Cound not get current locale'));

        $this->requestStackMock
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->subject->getCurrentLocale();
    }

    public function testLanguageCodes(): void
    {
        $expectedLanguageCodes = ['de', 'en', 'fr', 'it', 'nl', 'pl', 'pt', 'ru', 'es', 'sv', 'tr', 'zh'];;

        $this->translationServiceMock
            ->method('getLanguageCodes')
            ->willReturn($expectedLanguageCodes);

        $this->assertEquals($expectedLanguageCodes, $this->subject->getLanguageCodes());
    }

    public function testPlugins(): void
    {
        $expectedPlugins = ['plugin1', 'plugin2'];

        $this->pluginServiceMock
            ->method('getActiveList')
            ->willReturn($expectedPlugins);

        $this->assertEquals($expectedPlugins, $this->subject->getPlugins());
    }
}
