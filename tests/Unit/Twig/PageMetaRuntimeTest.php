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
}
