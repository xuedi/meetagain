<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\TranslationSuggestionStatus;
use App\Entity\User;
use App\Repository\MenuRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use App\Service\GlobalService;
use App\Service\PluginService;
use App\Service\TranslationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GlobalServiceTest extends TestCase
{
    public function testGetCurrentLocaleReturnsRequestLocale(): void
    {
        // Arrange: mock request with German locale
        $expectedLocale = 'de';

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($expectedLocale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act: get current locale
        $result = $subject->getCurrentLocale();

        // Assert: returns locale from request
        $this->assertSame($expectedLocale, $result);
    }

    public function testGetCurrentLocaleThrowsExceptionWhenNoRequest(): void
    {
        // Arrange: request stack returns null
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Assert: throws RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cound not get current locale');

        // Act: get current locale without request
        $subject->getCurrentLocale();
    }

    public function testGetLanguageCodesReturnsFromTranslationService(): void
    {
        // Arrange: mock translation service to return language codes
        $expectedCodes = ['de', 'en', 'fr'];

        $translationServiceStub = $this->createStub(TranslationService::class);
        $translationServiceStub->method('getLanguageCodes')->willReturn($expectedCodes);

        $subject = $this->createSubject(translationService: $translationServiceStub);

        // Act: get language codes
        $result = $subject->getLanguageCodes();

        // Assert: returns codes from translation service
        $this->assertSame($expectedCodes, $result);
    }

    public function testGetPluginsReturnsActivePlugins(): void
    {
        // Arrange: mock plugin service to return active plugins
        $expectedPlugins = ['plugin1', 'plugin2'];

        $pluginServiceStub = $this->createStub(PluginService::class);
        $pluginServiceStub->method('getActiveList')->willReturn($expectedPlugins);

        $subject = $this->createSubject(pluginService: $pluginServiceStub);

        // Act: get plugins
        $result = $subject->getPlugins();

        // Assert: returns active plugins
        $this->assertSame($expectedPlugins, $result);
    }

    public function testGetUserNameReturnsUserName(): void
    {
        // Arrange: mock user repository to return user with name
        $userId = 42;
        $expectedName = 'John Doe';

        $userStub = $this->createStub(User::class);
        $userStub->method('getName')->willReturn($expectedName);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($userStub);

        $subject = $this->createSubject(userRepo: $userRepoMock);

        // Act: get user name
        $result = $subject->getUserName($userId);

        // Assert: returns user's name
        $this->assertSame($expectedName, $result);
    }

    public function testHasNewMessagesReturnsTrueWhenSessionHasFlag(): void
    {
        // Arrange: mock session with new message flag set to true
        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')->with('hasNewMessage', false)->willReturn(true);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionStub);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: has new messages
        $this->assertTrue($subject->hasNewMessages());
    }

    public function testHasNewMessagesReturnsFalseWhenSessionHasNoFlag(): void
    {
        // Arrange: mock session with new message flag set to false
        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')->with('hasNewMessage', false)->willReturn(false);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionStub);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: no new messages
        $this->assertFalse($subject->hasNewMessages());
    }

    public function testHasNewMessagesReturnsFalseWhenNoRequest(): void
    {
        // Arrange: request stack returns null
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: returns false when no request available
        $this->assertFalse($subject->hasNewMessages());
    }

    public function testGetShowCookieConsentReturnsTrueWhenNoSession(): void
    {
        // Arrange: request stack returns null (no session)
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: show consent when no session
        $this->assertTrue($subject->getShowCookieConsent());
    }

    public function testGetShowCookieConsentReturnsTrueWhenConsentUnknown(): void
    {
        // Arrange: session with unknown consent status
        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')->willReturn('{}');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionStub);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: show consent when status unknown
        $this->assertTrue($subject->getShowCookieConsent());
    }

    public function testGetShowOsmReturnsTrueWhenNoSession(): void
    {
        // Arrange: request stack returns null (no session)
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: show OSM by default when no session
        $this->assertTrue($subject->getShowOsm());
    }

    public function testGetShowOsmReturnsTrueWhenConsentGranted(): void
    {
        // Arrange: session with OSM consent granted
        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')->willReturn('{"consent_cookies_osm": "granted"}');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionStub);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: show OSM when consent granted
        $this->assertTrue($subject->getShowOsm());
    }

    public function testGetAdminAttentionReturnsTrueWhenItemsNeedApproval(): void
    {
        // Arrange: dashboard service returns items needing approval
        $dashboardServiceStub = $this->createStub(DashboardService::class);
        $dashboardServiceStub->method('getNeedForApproval')->willReturn(['item1', 'item2']);

        $subject = $this->createSubject(dashboardService: $dashboardServiceStub);

        // Act & Assert: admin attention needed
        $this->assertTrue($subject->getAdminAttention());
    }

    public function testGetAdminAttentionReturnsFalseWhenNoItemsNeedApproval(): void
    {
        // Arrange: dashboard service returns empty array
        $dashboardServiceStub = $this->createStub(DashboardService::class);
        $dashboardServiceStub->method('getNeedForApproval')->willReturn([]);

        $subject = $this->createSubject(dashboardService: $dashboardServiceStub);

        // Act & Assert: no admin attention needed
        $this->assertFalse($subject->getAdminAttention());
    }

    public function testGetManagerAttentionReturnsTrueWhenSuggestionsRequested(): void
    {
        // Arrange: suggestion repo returns requested suggestions
        $suggestionRepoMock = $this->createMock(TranslationSuggestionRepository::class);
        $suggestionRepoMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => TranslationSuggestionStatus::Requested])
            ->willReturn(['suggestion1']);

        $subject = $this->createSubject(translationSuggestionRepo: $suggestionRepoMock);

        // Act & Assert: manager attention needed
        $this->assertTrue($subject->getManagerAttention());
    }

    public function testGetManagerAttentionReturnsFalseWhenNoSuggestionsRequested(): void
    {
        // Arrange: suggestion repo returns empty array
        $suggestionRepoMock = $this->createMock(TranslationSuggestionRepository::class);
        $suggestionRepoMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => TranslationSuggestionStatus::Requested])
            ->willReturn([]);

        $subject = $this->createSubject(translationSuggestionRepo: $suggestionRepoMock);

        // Act & Assert: no manager attention needed
        $this->assertFalse($subject->getManagerAttention());
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyWhenNoRequest(): void
    {
        // Arrange: request stack returns null
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: returns empty array when no request
        $this->assertSame([], $subject->getAlternativeLanguageCodes());
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyForProfilerUri(): void
    {
        // Arrange: request with profiler URI
        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getRequestUri')->willReturn('/_profiler/some/path');

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: returns empty array for profiler URIs
        $this->assertSame([], $subject->getAlternativeLanguageCodes());
    }

    public function testGetAlternativeLanguageCodesReturnsAlternativeLanguages(): void
    {
        // Arrange: mock request and translation service for alternative languages
        $currentLocale = 'en';
        $currentUri = '/some/path';
        $expectedAltLangList = ['de' => '/de/some/path', 'fr' => '/fr/some/path'];

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getRequestUri')->willReturn($currentUri);
        $requestStub->method('getLocale')->willReturn($currentLocale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $translationServiceMock = $this->createMock(TranslationService::class);
        $translationServiceMock
            ->expects($this->once())
            ->method('getAltLangList')
            ->with($currentLocale, $currentUri)
            ->willReturn($expectedAltLangList);

        $subject = $this->createSubject(
            requestStack: $requestStackStub,
            translationService: $translationServiceMock,
        );

        // Act: get alternative language codes
        $result = $subject->getAlternativeLanguageCodes();

        // Assert: returns alternative language URLs
        $this->assertSame($expectedAltLangList, $result);
    }

    public function testGetMenuReturnsMenuForCurrentLocaleAndType(): void
    {
        // Arrange: mock request with locale and menu repository
        $locale = 'de';
        $menuType = 'main';
        $user = $this->createStub(User::class);
        $expectedMenu = [['name' => 'Home', 'slug' => 'home']];

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($locale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($user);

        $menuRepoMock = $this->createMock(MenuRepository::class);
        $menuRepoMock
            ->expects($this->once())
            ->method('getAllSlugified')
            ->with(user: $user, locale: $locale, location: $menuType)
            ->willReturn($expectedMenu);

        $subject = $this->createSubject(
            requestStack: $requestStackStub,
            menuRepo: $menuRepoMock,
            security: $securityStub,
        );

        // Act: get menu
        $result = $subject->getMenu($menuType);

        // Assert: returns menu items
        $this->assertSame($expectedMenu, $result);
    }

    public function testGetMenuReturnsDefaultMenuWhenNoRequest(): void
    {
        // Arrange: request stack returns null
        $menuType = 'main';
        $user = $this->createStub(User::class);
        $expectedMenu = [['name' => 'Home', 'slug' => 'home']];

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($user);

        $menuRepoMock = $this->createMock(MenuRepository::class);
        $menuRepoMock
            ->expects($this->once())
            ->method('getAllSlugified')
            ->with(user: $user)
            ->willReturn($expectedMenu);

        $subject = $this->createSubject(
            requestStack: $requestStackStub,
            menuRepo: $menuRepoMock,
            security: $securityStub,
        );

        // Act: get menu without request
        $result = $subject->getMenu($menuType);

        // Assert: returns default menu
        $this->assertSame($expectedMenu, $result);
    }

    private function createSubject(
        ?RequestStack $requestStack = null,
        ?TranslationService $translationService = null,
        ?DashboardService $dashboardService = null,
        ?UserRepository $userRepo = null,
        ?PluginService $pluginService = null,
        ?MenuRepository $menuRepo = null,
        ?TranslationSuggestionRepository $translationSuggestionRepo = null,
        ?Security $security = null,
    ): GlobalService {
        return new GlobalService(
            requestStack: $requestStack ?? $this->createStub(RequestStack::class),
            translationService: $translationService ?? $this->createStub(TranslationService::class),
            dashboardService: $dashboardService ?? $this->createStub(DashboardService::class),
            userRepo: $userRepo ?? $this->createStub(UserRepository::class),
            pluginService: $pluginService ?? $this->createStub(PluginService::class),
            menuRepo: $menuRepo ?? $this->createStub(MenuRepository::class),
            translationSuggestionRepo: $translationSuggestionRepo ?? $this->createStub(TranslationSuggestionRepository::class),
            security: $security ?? $this->createStub(Security::class),
        );
    }
}
