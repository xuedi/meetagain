<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Publisher\MetaDescription\MetaDescriptionProviderInterface;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\NoindexService;
use App\Twig\PageMetaRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class PageMetaRuntimeTest extends TestCase
{
    private Stub&RequestStack $requestStackStub;
    private Stub&ConfigService $configServiceStub;
    private Stub&CanonicalUrlService $canonicalUrlServiceStub;
    private Stub&SiteNameResolver $siteNameResolverStub;
    private Stub&NoindexService $noindexServiceStub;
    private PageMetaRuntime $subject;

    protected function setUp(): void
    {
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->canonicalUrlServiceStub = $this->createStub(CanonicalUrlService::class);
        $this->siteNameResolverStub = $this->createStub(SiteNameResolver::class);
        $this->noindexServiceStub = $this->createStub(NoindexService::class);
        $this->subject = new PageMetaRuntime(
            $this->requestStackStub,
            $this->configServiceStub,
            $this->canonicalUrlServiceStub,
            $this->siteNameResolverStub,
            $this->noindexServiceStub,
            new IdentityTranslator(),
        );
    }

    public function testIsNoindexDelegatesToTheChain(): void
    {
        // Arrange
        $this->requestStackStub->method('getCurrentRequest')->willReturn(new Request());
        $this->noindexServiceStub->method('shouldNoindex')->willReturn(true);

        // Act
        $result = $this->subject->isNoindex();

        // Assert
        static::assertTrue($result);
    }

    public function testIsNoindexIsFalseWithoutARequest(): void
    {
        // Arrange
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);
        $this->noindexServiceStub->method('shouldNoindex')->willReturn(true);

        // Act
        $result = $this->subject->isNoindex();

        // Assert
        static::assertFalse($result);
    }

    public function testGetCanonicalUrlDelegatesToCanonicalUrlService(): void
    {
        // Arrange
        $this->requestStackStub->method('getCurrentRequest')->willReturn(new Request());
        $this->canonicalUrlServiceStub->method('getCanonicalUrl')->willReturn('https://meetagain.local/en/events');

        // Act
        $result = $this->subject->getCanonicalUrl();

        // Assert
        static::assertSame('https://meetagain.local/en/events', $result);
    }

    public function testGetCanonicalUrlReturnsFallbackWhenNoRequest(): void
    {
        // Arrange
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);
        $this->configServiceStub->method('getHost')->willReturn('https://meetagain.local/');

        // Act
        $result = $this->subject->getCanonicalUrl();

        // Assert
        static::assertSame('https://meetagain.local/', $result);
    }

    public function testGetSiteNameDelegatesToResolver(): void
    {
        // Arrange
        $this->siteNameResolverStub->method('resolve')->willReturn('Weiqi Club');

        // Act
        $result = $this->subject->getSiteName();

        // Assert
        static::assertSame('Weiqi Club', $result);
    }

    public function testGetMetaDescriptionReturnsProviderValueWhenAvailable(): void
    {
        // Arrange
        $provider = $this->createStub(MetaDescriptionProviderInterface::class);
        $provider->method('getMetaDescription')->willReturn('Weiqi club upcoming events');

        $subject = new PageMetaRuntime(
            $this->requestStackStub,
            $this->configServiceStub,
            $this->canonicalUrlServiceStub,
            $this->siteNameResolverStub,
            $this->noindexServiceStub,
            new IdentityTranslator(),
            [$provider],
        );

        // Act
        $result = $subject->getMetaDescription('events');

        // Assert
        static::assertSame('Weiqi club upcoming events', $result);
    }

    public function testGetMetaDescriptionFallsBackToSystemConfigWhenNoProviderValue(): void
    {
        // Arrange
        $this->configServiceStub->method('getSeoDescription')->willReturn('System events description');

        // Act
        $result = $this->subject->getMetaDescription('events');

        // Assert
        static::assertSame('System events description', $result);
    }

    public function testGetMetaDescriptionFallsBackToATranslatedLineWhenNothingConfigured(): void
    {
        // Arrange
        $this->configServiceStub->method('getSeoDescription')->willReturn('');

        // Act
        $result = $this->subject->getMetaDescription('members');

        // Assert
        static::assertSame('chrome.meta_description_members', $result);
    }

    #[DataProvider('pageFallbackProvider')]
    public function testThePageFallbackSitsBetweenTheChainAndTheSystemValue(?string $providerValue, ?string $pageFallback, string $expected): void
    {
        // Arrange
        $provider = $this->createStub(MetaDescriptionProviderInterface::class);
        $provider->method('getMetaDescription')->willReturn($providerValue);
        $this->configServiceStub->method('getSeoDescription')->willReturn('System description');
        $subject = new PageMetaRuntime(
            $this->requestStackStub,
            $this->configServiceStub,
            $this->canonicalUrlServiceStub,
            $this->siteNameResolverStub,
            $this->noindexServiceStub,
            new IdentityTranslator(),
            [$provider],
        );

        // Act
        $result = $subject->getMetaDescription('default', $pageFallback);

        // Assert
        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function pageFallbackProvider(): iterable
    {
        yield 'a provider value beats the page' => ['Group text', 'Page excerpt', 'Group text'];
        yield 'the page beats the system value' => [null, 'Page excerpt', 'Page excerpt'];
        yield 'an empty page fallback falls through' => [null, '', 'System description'];
    }

    public function testLdJsonKeepsAClosingScriptTagFromEndingTheBlock(): void
    {
        // Arrange
        $title = '</script><script>alert(1)</script>';

        // Act
        $result = (string) $this->subject->ldJson(['name' => $title]);

        // Assert
        static::assertStringNotContainsString('</script>', $result);
        static::assertStringNotContainsString('<', $result);
        static::assertSame($title, json_decode($result, true)['name']);
    }

    public function testLdJsonEscapesTheOtherHtmlSignificantCharacters(): void
    {
        // Arrange
        $payload = ['name' => 'Tom & "Jerry" O\'Brien', 'url' => 'https://example.org/a/b'];

        // Act
        $result = (string) $this->subject->ldJson($payload);

        // Assert
        static::assertStringNotContainsString('&', $result);
        static::assertStringNotContainsString("'", $result);
        static::assertStringContainsString('https:\\/\\/example.org', $result);
        static::assertSame($payload, json_decode($result, true));
    }

    public function testLdJsonLeavesNonAsciiTextReadable(): void
    {
        // Act
        $result = (string) $this->subject->ldJson(['name' => 'Königsallee 北京']);

        // Assert
        static::assertStringContainsString('Königsallee 北京', $result);
    }

    public function testOrganizationSchemaFromAProviderIsEscapedToo(): void
    {
        // Arrange
        $provider = new class implements OrganizationSchemaProviderInterface {
            public function getOrganizationSchema(): ?array
            {
                return ['@type' => 'Organization', 'name' => '</script><script>alert(1)</script>'];
            }
        };
        $subject = new PageMetaRuntime(
            $this->requestStackStub,
            $this->configServiceStub,
            $this->canonicalUrlServiceStub,
            $this->siteNameResolverStub,
            $this->noindexServiceStub,
            new IdentityTranslator(),
            [],
            [$provider],
        );

        // Act
        $result = (string) $subject->getOrganizationSchema();

        // Assert
        static::assertStringNotContainsString('</script>', $result);
        static::assertSame('https://schema.org', json_decode($result, true)['@context']);
    }

    #[DataProvider('pageTitleProvider')]
    public function testPageTitleNamesTheSiteAndItsPlace(string $title, string $place, string $expected): void
    {
        // Arrange
        $subject = $this->titleRuntime('Weiqi Club');

        // Act
        $result = $subject->getPageTitle($title, $place);

        // Assert
        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pageTitleProvider(): iterable
    {
        yield 'a title gets the site name' => ['About', '', 'About - Weiqi Club'];
        yield 'a title gets the site name and the place' => ['About', 'Berlin', 'About - Weiqi Club, Berlin'];
        yield 'no title serves the site name alone' => ['', '', 'Weiqi Club'];
        yield 'no title serves the site name with the place' => ['', 'Berlin', 'Weiqi Club, Berlin'];
        yield 'a title naming the site stays alone' => ['Welcome to the weiqi club', 'Berlin', 'Welcome to the weiqi club'];
        yield 'punctuation and spacing do not hide the site name' => ['weiqi-club.de - Go in Berlin', 'Berlin', 'weiqi-club.de - Go in Berlin'];
        yield 'a title naming the place drops it' => ['Go evenings in Berlin', 'Berlin', 'Go evenings in Berlin - Weiqi Club'];
        yield 'the place goes first when the length runs out' => [str_repeat('a', 35), 'Charlottenburg', str_repeat('a', 35) . ' - Weiqi Club'];
        yield 'a long title keeps its words over the site name' => [str_repeat('a', 60), '', str_repeat('a', 60)];
        yield 'escaped markup is read as text' => ['Q&amp;A', '', 'Q&A - Weiqi Club'];
        yield 'whitespace collapses' => ["  Rules\n  and   dates ", '', 'Rules and dates - Weiqi Club'];
    }

    public function testATitleAtTheLengthLimitStillGetsTheSiteName(): void
    {
        // Arrange
        $title = str_repeat('a', PageMetaRuntime::TITLE_MAX_LENGTH - strlen(' - Weiqi Club'));

        // Act
        $result = $this->titleRuntime('Weiqi Club')->getPageTitle($title);

        // Assert
        static::assertSame($title . ' - Weiqi Club', $result);
    }

    public function testAWideCharacterCountsTwice(): void
    {
        // Arrange
        $latin = str_repeat('a', 22);
        $chinese = str_repeat('围', 22);
        $subject = $this->titleRuntime('Weiqi Club');

        // Act
        $latinTitle = $subject->getPageTitle($latin);
        $chineseTitle = $subject->getPageTitle($chinese);

        // Assert
        static::assertSame($latin . ' - Weiqi Club', $latinTitle);
        static::assertSame($chinese, $chineseTitle);
    }

    #[DataProvider('titleDetailProvider')]
    public function testTheDetailJoinsFirstAndGoesLast(string $title, string $expected): void
    {
        // Arrange
        $subject = $this->titleRuntime('Weiqi Club');

        // Act
        $result = $subject->getPageTitle($title, '', '2026-11-17');

        // Assert
        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function titleDetailProvider(): iterable
    {
        yield 'detail and site both fit' => ['Go night', 'Go night - 2026-11-17 - Weiqi Club'];
        yield 'the site goes before the detail' => [str_repeat('a', 30), str_repeat('a', 30) . ' - 2026-11-17'];
        yield 'the detail goes last' => [str_repeat('a', 45), str_repeat('a', 45)];
    }

    #[DataProvider('pageDescriptionProvider')]
    public function testPageDescriptionFitsTheSnippet(string $text, string $suffix, string $expected): void
    {
        // Act
        $result = $this->subject->getPageDescription($text, $suffix);

        // Assert
        static::assertSame($expected, $result);
        static::assertLessThanOrEqual(PageMetaRuntime::DESCRIPTION_MAX_LENGTH, mb_strwidth($result));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pageDescriptionProvider(): iterable
    {
        $long = str_repeat('weiqi ', 40);

        yield 'a short text stays untouched' => ['We play Go.', '', 'We play Go.'];
        yield 'entities are decoded and whitespace collapses' => ["Q&amp;A \n  night", '', 'Q&A night'];
        yield 'a long text is cut before a word' => [$long, '', rtrim(str_repeat('weiqi ', 23)) . '…'];
        yield 'the suffix survives whole' => [$long, 'meeting at 2026-11-17 19:00', rtrim(str_repeat('weiqi ', 18)) . '… - meeting at 2026-11-17 19:00'];
        yield 'a text without spaces is cut at a character' => [str_repeat('围棋', 100), '', str_repeat('围棋', 34) . '围…'];
        yield 'a short text keeps its suffix' => ['Go night', 'meeting at 2026-11-17 19:00', 'Go night - meeting at 2026-11-17 19:00'];
    }

    private function titleRuntime(string $siteName): PageMetaRuntime
    {
        $siteNameResolver = $this->createStub(SiteNameResolver::class);
        $siteNameResolver->method('resolve')->willReturn($siteName);
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource(
            'array',
            [
                'chrome.title_site_place' => '%site%, %place%',
                'chrome.title_page_site' => '%title% - %site%',
                'chrome.title_page_site_place' => '%title% - %site%, %place%',
                'chrome.title_page_detail' => '%title% - %detail%',
            ],
            'en',
        );

        return new PageMetaRuntime(
            $this->requestStackStub,
            $this->configServiceStub,
            $this->canonicalUrlServiceStub,
            $siteNameResolver,
            $this->noindexServiceStub,
            $translator,
        );
    }
}
