<?php declare(strict_types=1);

namespace Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CalendarFeedTest extends WebTestCase
{
    public function testFeedServesACalendarDocument(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/events.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=UTF-8');

        $body = (string) $client->getResponse()->getContent();
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $body);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $body);
        self::assertStringContainsString('BEGIN:VEVENT', $body);
    }

    public function testMatchingEtagYieldsNotModified(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/en/events.ics');
        self::assertResponseIsSuccessful();
        $etag = $client->getResponse()->getEtag();
        self::assertNotNull($etag);

        $client->request('GET', '/en/events.ics', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
        self::assertEmpty($client->getResponse()->getContent());
    }

    public function testLocalesRenderTheirOwnUrls(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/events.ics');
        $english = (string) $client->getResponse()->getContent();

        $client->request('GET', '/de/events.ics');
        $german = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('/en/event/', $english);
        self::assertStringContainsString('/de/event/', $german);
    }

    public function testCachedEntriesDoNotLeakBetweenHosts(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', 'http://localhost/en/events.ics');
        self::assertResponseIsSuccessful();

        $client->request('GET', 'http://other.example/en/events.ics');
        self::assertResponseIsSuccessful();

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('other.example', $body);
        self::assertStringNotContainsString('URL:http://localhost', $body);
    }

    public function testSingleEventDownloadServesOneEvent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/events.ics');
        preg_match('/UID:event-(\d+)@/', (string) $client->getResponse()->getContent(), $matches);
        self::assertNotEmpty($matches);

        $client->request('GET', sprintf('/en/event/%d.ics', (int) $matches[1]));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=UTF-8');
        self::assertSame(1, substr_count((string) $client->getResponse()->getContent(), 'BEGIN:VEVENT'));
    }

    public function testSingleEventDownloadReturnsNotFoundForUnknownEvent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/999999.ics');

        self::assertResponseStatusCodeSame(404);
    }
}
