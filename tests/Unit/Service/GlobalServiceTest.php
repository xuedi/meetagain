<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\User;
use App\Repository\MenuRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use App\Service\GlobalService;
use App\Service\PluginService;
use App\Service\TranslationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[AllowMockObjectsWithoutExpectations]
class GlobalServiceTest extends TestCase
{
    private MockObject|RequestStack $requestStackMock;
    private MockObject|TranslationService $translationServiceMock;
    private MockObject|DashboardService $dashboardServiceMock;
    private MockObject|UserRepository $userRepositoryMock;
    private MockObject|MenuRepository $menuRepositoryMock;
    private MockObject|PluginService $pluginServiceMock;
    private MockObject|TranslationSuggestionRepository $suggestionRepoMock;
    private MockObject|Security $securityMock;
    private GlobalService $subject;

    protected function setUp(): void
    {
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->translationServiceMock = $this->createMock(TranslationService::class);
        $this->dashboardServiceMock = $this->createMock(DashboardService::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->menuRepositoryMock = $this->createMock(MenuRepository::class);
        $this->suggestionRepoMock = $this->createMock(TranslationSuggestionRepository::class);
        $this->pluginServiceMock = $this->createMock(PluginService::class);
        $this->securityMock = $this->createMock(Security::class);

        $this->subject = new GlobalService(
            requestStack: $this->requestStackMock,
            translationService: $this->translationServiceMock,
            dashboardService: $this->dashboardServiceMock,
            userRepo: $this->userRepositoryMock,
            pluginService: $this->pluginServiceMock,
            menuRepo: $this->menuRepositoryMock,
            translationSuggestionRepo: $this->suggestionRepoMock,
            security: $this->securityMock,
        );
    }

    public function testCurrentLocale(): void
    {
        $expected = 'de';

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getLocale')->willReturn($expected);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertEquals($expected, $this->subject->getCurrentLocale());
    }

    public function testCatchUnknownCurrentLocale(): void
    {
        $this->expectExceptionObject(new RuntimeException('Cound not get current locale'));

        $this->requestStackMock->method('getCurrentRequest')->willReturn(null);

        $this->subject->getCurrentLocale();
    }

    public function testLanguageCodes(): void
    {
        $expectedLanguageCodes = ['de', 'en', 'fr', 'it', 'nl', 'pl', 'pt', 'ru', 'es', 'sv', 'tr', 'zh'];;

        $this->translationServiceMock->method('getLanguageCodes')->willReturn($expectedLanguageCodes);

        $this->assertEquals($expectedLanguageCodes, $this->subject->getLanguageCodes());
    }

    public function testPlugins(): void
    {
        $expectedPlugins = ['plugin1', 'plugin2'];

        $this->pluginServiceMock->method('getActiveList')->willReturn($expectedPlugins);

        $this->assertEquals($expectedPlugins, $this->subject->getPlugins());
    }

    public function testGetUserName(): void
    {
        $userId = 42;
        $expectedName = 'John Doe';

        $userMock = $this->createMock(User::class);
        $userMock->method('getName')->willReturn($expectedName);

        $this->userRepositoryMock
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($userMock);

        $this->assertEquals($expectedName, $this->subject->getUserName($userId));
    }

    public function testHasNewMessagesTrue(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->with('hasNewMessage', false)->willReturn(true);

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getSession')->willReturn($sessionMock);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertTrue($this->subject->hasNewMessages());
    }

    public function testHasNewMessagesFalse(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->with('hasNewMessage', false)->willReturn(false);

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getSession')->willReturn($sessionMock);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertFalse($this->subject->hasNewMessages());
    }

    public function testHasNewMessagesNoRequest(): void
    {
        $this->requestStackMock->method('getCurrentRequest')->willReturn(null);

        $this->assertFalse($this->subject->hasNewMessages());
    }

    public function testGetShowCookieConsentNoSession(): void
    {
        $this->requestStackMock->method('getCurrentRequest')->willReturn(null);

        $this->assertTrue($this->subject->getShowCookieConsent());
    }

    public function testGetShowCookieConsentUnknown(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->willReturn('{}');

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getSession')->willReturn($sessionMock);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertTrue($this->subject->getShowCookieConsent());
    }

    public function testGetShowOsmNoSession(): void
    {
        $this->requestStackMock->method('getCurrentRequest')->willReturn(null);

        $this->assertTrue($this->subject->getShowOsm());
    }

    public function testGetShowOsmNoSessionGranted(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->willReturn('{"consent_cookies_osm": "granted"}');

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getSession')->willReturn($sessionMock);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertTrue($this->subject->getShowOsm());
    }

    public function testGetAdminAttentionTrue(): void
    {
        $this->dashboardServiceMock->method('getNeedForApproval')->willReturn(['item1', 'item2']);

        $this->assertTrue($this->subject->getAdminAttention());
    }

    public function testGetAdminAttentionFalse(): void
    {
        $this->dashboardServiceMock->method('getNeedForApproval')->willReturn([]);

        $this->assertFalse($this->subject->getAdminAttention());
    }

    public function testGetAlternativeLanguageCodesNoRequest(): void
    {
        $this->requestStackMock->method('getCurrentRequest')->willReturn(null);

        $this->assertEquals([], $this->subject->getAlternativeLanguageCodes());
    }

    public function testGetAlternativeLanguageCodesProfilerUri(): void
    {
        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getRequestUri')->willReturn('/_profiler/some/path');

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->assertEquals([], $this->subject->getAlternativeLanguageCodes());
    }

    public function testGetAlternativeLanguageCodes(): void
    {
        $currentLocale = 'en';
        $currentUri = '/some/path';
        $expectedAltLangList = ['de' => '/de/some/path', 'fr' => '/fr/some/path'];

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getRequestUri')->willReturn($currentUri);
        $requestMock->method('getLocale')->willReturn($currentLocale);

        $this->requestStackMock->method('getCurrentRequest')->willReturn($requestMock);

        $this->translationServiceMock
            ->method('getAltLangList')
            ->with($currentLocale, $currentUri)
            ->willReturn($expectedAltLangList);

        $this->assertEquals($expectedAltLangList, $this->subject->getAlternativeLanguageCodes());
    }
}
