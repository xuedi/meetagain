<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Publisher\MetaDescription\MetaDescriptionProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\NoindexService;
use App\Twig\PageMetaRuntime;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

    public function testGetMetaDescriptionFallsBackToHardcodedWhenNothingConfigured(): void
    {
        // Arrange
        $this->configServiceStub->method('getSeoDescription')->willReturn('');

        // Act
        $result = $this->subject->getMetaDescription('members');

        // Assert
        static::assertSame('Meet the members of this community.', $result);
    }
}
