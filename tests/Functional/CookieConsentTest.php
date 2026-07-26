<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Session\Consent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CookieConsentTest extends WebTestCase
{
    private function cookieToken(object $client, string $intention): string
    {
        $endpoint = $intention === 'cookie_accept' ? '/ajax/cookie/accept' : '/ajax/cookie/deny';
        $crawler = $client->request('GET', '/en/');

        return (string) $crawler->filter(sprintf('button.cookieTrigger[data-url="%s"]', $endpoint))->attr('data-token');
    }

    public function testAcceptCookiesEndpointReturnsSuccess(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_accept');

        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAcceptCookiesSetsCookies(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_accept');

        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token]);

        $cookies = $client->getCookieJar();
        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);

        static::assertNotNull($consentCookie, 'Cookie consent cookie should be set');
        static::assertSame('granted', $consentCookie->getValue());
    }

    public function testAcceptCookiesWithOsmConsentGranted(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_accept');

        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token, 'osmConsent' => 'true']);

        $cookies = $client->getCookieJar();
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($osmCookie, 'OSM consent cookie should be set when osmConsent=true');
        static::assertSame('granted', $osmCookie->getValue());
    }

    public function testAcceptCookiesWithOsmConsentDenied(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_accept');

        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token, 'osmConsent' => 'false']);

        $cookies = $client->getCookieJar();
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($osmCookie, 'OSM consent cookie should be set even when denied');
        static::assertSame('denied', $osmCookie->getValue());
    }

    public function testDenyCookiesEndpointReturnsSuccess(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_deny');

        $client->request('POST', '/ajax/cookie/deny', ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testDenyCookiesPersistsDeniedPreference(): void
    {
        $client = static::createClient();

        $acceptToken = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $acceptToken, 'osmConsent' => 'true']);

        $denyToken = $this->cookieToken($client, 'cookie_deny');
        $client->request('POST', '/ajax/cookie/deny', ['_token' => $denyToken]);

        $cookies = $client->getCookieJar();
        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($consentCookie, 'Deny must persist a functional consent cookie, not delete it');
        static::assertSame('denied', $consentCookie->getValue());
        static::assertNotNull($osmCookie);
        static::assertSame('denied', $osmCookie->getValue());
    }

    public function testCookieConsentResponseIsJson(): void
    {
        $client = static::createClient();
        $token = $this->cookieToken($client, 'cookie_accept');

        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token]);

        $response = $client->getResponse();
        static::assertJson($response->getContent());
    }

    public function testFullCookieAcceptFlow(): void
    {
        $client = static::createClient();

        $token = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token, 'osmConsent' => 'true']);
        $this->assertResponseIsSuccessful();

        $cookies = $client->getCookieJar();

        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($consentCookie);
        static::assertNotNull($osmCookie);
        static::assertSame('granted', $consentCookie->getValue());
        static::assertSame('granted', $osmCookie->getValue());

        $token2 = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token2]);
        $this->assertResponseIsSuccessful();
    }

    public function testFullCookieDenyFlow(): void
    {
        $client = static::createClient();

        $token = $this->cookieToken($client, 'cookie_deny');
        $client->request('POST', '/ajax/cookie/deny', ['_token' => $token]);
        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        static::assertJson($response->getContent());
        static::assertStringContainsString('Saved preferences', $response->getContent());

        $consentCookie = $client->getCookieJar()->get(Consent::TYPE_COOKIES);
        static::assertNotNull($consentCookie);
        static::assertSame('denied', $consentCookie->getValue());
    }

    public function testCookiePageLoads(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/cookie/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Cookie consent form should be displayed');
    }

    public function testCookiePageFormSubmitAccept(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/cookie/');
        $this->assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('cookie_consent_save')
            ->form([
                'cookie_consent[cookies]' => true,
                'cookie_consent[osm]' => true,
            ]);
        $client->submit($form);

        $this->assertResponseRedirects('/en/cookie/');

        $cookies = $client->getCookieJar();
        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($consentCookie);
        static::assertSame('granted', $consentCookie->getValue());
        static::assertNotNull($osmCookie);
        static::assertSame('granted', $osmCookie->getValue());
    }

    public function testCookiePageFormSubmitDeny(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/cookie/');
        $this->assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('cookie_consent_save')
            ->form([
                'cookie_consent[cookies]' => false,
            ]);
        $client->submit($form);

        $this->assertResponseRedirects('/en/cookie/');

        $consentCookie = $client->getCookieJar()->get(Consent::TYPE_COOKIES);
        static::assertNotNull($consentCookie);
        static::assertSame('denied', $consentCookie->getValue());
    }

    public function testBannerFormActionPointsToCookiePage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/');

        $form = $crawler->filter('#dropdown-cookie form');
        static::assertCount(1, $form, 'Cookie banner form should exist');
        static::assertStringContainsString('/en/cookie/', $form->attr('action'));
    }
}
