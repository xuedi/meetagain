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
    public function testAcceptCookiesEndpointReturnsSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/accept');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAcceptCookiesSetsCookies(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/accept');

        $cookies = $client->getCookieJar();
        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);

        $this->assertNotNull($consentCookie, 'Cookie consent cookie should be set');
        $this->assertSame('granted', $consentCookie->getValue());
    }

    public function testAcceptCookiesWithOsmConsentGranted(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/accept?osmConsent=true');

        $cookies = $client->getCookieJar();
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        $this->assertNotNull($osmCookie, 'OSM consent cookie should be set when osmConsent=true');
        $this->assertSame('granted', $osmCookie->getValue());
    }

    public function testAcceptCookiesWithOsmConsentDenied(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/accept?osmConsent=false');

        $cookies = $client->getCookieJar();
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        $this->assertNotNull($osmCookie, 'OSM consent cookie should be set even when denied');
        $this->assertSame('denied', $osmCookie->getValue());
    }

    public function testDenyCookiesEndpointReturnsSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/deny');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testDenyCookiesClearsCookies(): void
    {
        $client = static::createClient();

        // First accept cookies
        $client->request('GET', '/ajax/cookie/accept?osmConsent=true');

        // Then deny them
        $client->request('GET', '/ajax/cookie/deny');

        // Check that cookies are cleared (expired)
        $response = $client->getResponse();
        $setCookieHeaders = $response->headers->all('set-cookie');

        // The deny endpoint should clear the cookies
        $this->assertNotEmpty($setCookieHeaders, 'Response should contain Set-Cookie headers to clear cookies');
    }

    public function testCookieConsentResponseIsJson(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ajax/cookie/accept');

        $response = $client->getResponse();
        $this->assertJson($response->getContent());
    }

    public function testFullCookieAcceptFlow(): void
    {
        $client = static::createClient();

        // Step 1: User visits a page (simulated by visiting the ajax endpoint)
        // In a real scenario, this would be the homepage with the cookie banner

        // Step 2: User clicks "Accept" with OSM consent checked
        $client->request('GET', '/ajax/cookie/accept?osmConsent=true');
        $this->assertResponseIsSuccessful();

        // Step 3: Verify cookies are set correctly
        $cookies = $client->getCookieJar();

        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        $this->assertNotNull($consentCookie);
        $this->assertNotNull($osmCookie);
        $this->assertSame('granted', $consentCookie->getValue());
        $this->assertSame('granted', $osmCookie->getValue());

        // Step 4: Subsequent requests should include these cookies
        // (The client automatically sends cookies on subsequent requests)
        $client->request('GET', '/ajax/cookie/accept');
        $this->assertResponseIsSuccessful();
    }

    public function testFullCookieDenyFlow(): void
    {
        $client = static::createClient();

        // Step 1: User clicks "Deny"
        $client->request('GET', '/ajax/cookie/deny');
        $this->assertResponseIsSuccessful();

        // Step 2: Verify response indicates cookies should be cleared
        $response = $client->getResponse();
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Saved preferences', $response->getContent());
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

        $form = $crawler->selectButton('cookie_consent_save')->form([
            'cookie_consent[cookies]' => true,
            'cookie_consent[osm]' => true,
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/en/cookie/');

        $cookies = $client->getCookieJar();
        $consentCookie = $cookies->get(Consent::TYPE_COOKIES);
        $osmCookie = $cookies->get(Consent::TYPE_OSM);

        $this->assertNotNull($consentCookie);
        $this->assertSame('granted', $consentCookie->getValue());
        $this->assertNotNull($osmCookie);
        $this->assertSame('granted', $osmCookie->getValue());
    }

    public function testCookiePageFormSubmitDeny(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/cookie/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('cookie_consent_save')->form([
            'cookie_consent[cookies]' => false,
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/en/cookie/');
    }

    public function testBannerFormActionPointsToCookiePage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/');

        $form = $crawler->filter('#dropdown-cookie form');
        $this->assertCount(1, $form, 'Cookie banner form should exist');
        $this->assertStringContainsString('/en/cookie/', $form->attr('action'));
    }
}
