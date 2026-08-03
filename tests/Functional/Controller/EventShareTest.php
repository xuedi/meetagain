<?php declare(strict_types=1);

namespace Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventShareTest extends WebTestCase
{
    public function testTheSharePageRendersTheSheetAndStaysOutOfTheIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/1/share');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.event-share-sheet [data-share-link]');
        self::assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
    }

    public function testTheModalRouteServesTheBareSheetToAnXhrCaller(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/1/share/modal', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('event-share-sheet', $body);
        self::assertStringNotContainsString('<html', $body);
    }

    public function testADirectHitOnTheModalRouteLandsOnTheSharePage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/1/share/modal');

        self::assertResponseRedirects('/en/event/1/share');
    }

    public function testTheQrRouteServesADownloadablePng(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/1/share/qr.png');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnUnknownEventHasNoShareSheet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/999999/share');

        self::assertResponseStatusCodeSame(404);
    }
}
