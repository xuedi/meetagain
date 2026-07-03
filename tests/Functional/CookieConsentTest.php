<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Session\Consent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the cookie consent click path.
 *
 * These tests verify that:
 * 1. The cookie accept/deny endpoints work correctly
 * 2. Cookies are set/cleared appropriately
 * 3. The consent state is persisted in the session
 *
 * This serves as an example for testing other click paths in the application.
 */
class CookieConsentTest extends WebTestCase
{
    /**
     * Extract a CSRF token from the cookie banner rendered on the homepage.
     * The banner includes data-token attributes for both accept and deny endpoints.
     */
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

        // Accept first so the consent cookies exist as 'granted'...
        $acceptToken = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $acceptToken, 'osmConsent' => 'true']);

        // ...then deny. The preference must be persisted as 'denied', not deleted - otherwise the
        // client-side banner (which keys off the cookie's presence) reopens on reload (the reject loop).
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

        // Step 1: User visits a page (simulated by visiting the ajax endpoint)
        // In a real scenario, this would be the homepage with the cookie banner

        // Step 2: User clicks "Accept" with OSM consent checked
        $token = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token, 'osmConsent' => 'true']);
        $this->assertResponseIsSuccessful();

        // Step 3: Verify cookies are set correctly
        $cookies = $client->getCookieJar();

        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        static::assertNotNull($consentCookie);
        static::assertNotNull($osmCookie);
        static::assertSame('granted', $consentCookie->getValue());
        static::assertSame('granted', $osmCookie->getValue());

        // Step 4: Subsequent requests should include these cookies
        // (The client automatically sends cookies on subsequent requests)
        $token2 = $this->cookieToken($client, 'cookie_accept');
        $client->request('POST', '/ajax/cookie/accept', ['_token' => $token2]);
        $this->assertResponseIsSuccessful();
    }

    public function testFullCookieDenyFlow(): void
    {
        $client = static::createClient();

        // Step 1: User clicks "Deny" without a prior choice
        $token = $this->cookieToken($client, 'cookie_deny');
        $client->request('POST', '/ajax/cookie/deny', ['_token' => $token]);
        $this->assertResponseIsSuccessful();

        // Step 2: Response is the saved-preferences JSON
        $response = $client->getResponse();
        static::assertJson($response->getContent());
        static::assertStringContainsString('Saved preferences', $response->getContent());

        // Step 3: The rejection is stored as a functional cookie so the banner stays closed on reload
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

        // Deny on the manage page persists 'denied' too, so the banner does not reopen.
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
