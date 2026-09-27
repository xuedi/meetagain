<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\FooterLinkResolver;
use App\Emails\RequestSendingIdentityProvider;
use App\Service\Config\SiteNameResolver;
use App\Service\Http\RequestHostResolver;
use App\Service\Media\SiteLogoResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RequestSendingIdentityProviderTest extends TestCase
{
    public function testItAnswersFromTheRequestWhateverTheOrigin(): void
    {
        // Arrange
        $provider = $this->provider($this->footerLinks());

        // Act
        $identity = $provider->resolve(new stdClass(), 'en');

        // Assert
        static::assertSame('Example Site', $identity->siteName);
        static::assertSame('https://example.org', $identity->siteUrl);
        static::assertSame('https://example.org/logo.png', $identity->logoUrl);
        static::assertSame('Example Site', $identity->greeting);
        static::assertNull($identity->attribution);
    }

    public function testTheFooterAsksForTheRequestHostAndTheWholeFlaggedSet(): void
    {
        // Arrange
        $footerLinks = $this->createMock(FooterLinkResolver::class);
        $footerLinks
            ->expects($this->once())
            ->method('resolve')
            ->with('https://example.org/', 'de', null)
            ->willReturn([['label' => 'Impressum', 'url' => 'https://example.org/de/imprint']]);

        // Act
        $identity = $this->provider($footerLinks)->resolve(null, 'de');

        // Assert
        static::assertSame([['label' => 'Impressum', 'url' => 'https://example.org/de/imprint']], $identity->links);
    }

    private function provider(FooterLinkResolver $footerLinks): RequestSendingIdentityProvider
    {
        $siteName = $this->createStub(SiteNameResolver::class);
        $siteName->method('resolve')->willReturn('Example Site');

        $host = $this->createStub(RequestHostResolver::class);
        $host->method('getSchemeAndHost')->willReturn('https://example.org/');

        $logo = $this->createStub(SiteLogoResolver::class);
        $logo->method('endpointUrl')->willReturnCallback(static fn(string $siteUrl): string => $siteUrl . '/logo.png');

        return new RequestSendingIdentityProvider($siteName, $host, $logo, $footerLinks);
    }

    private function footerLinks(): FooterLinkResolver
    {
        $footerLinks = $this->createStub(FooterLinkResolver::class);
        $footerLinks->method('resolve')->willReturn([]);

        return $footerLinks;
    }
}
