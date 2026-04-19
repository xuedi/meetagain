<?php declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventApiControllerTest extends WebTestCase
{
    public function testListReturnsJsonShape(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/events');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('items', $body);
        self::assertArrayHasKey('total', $body);
        self::assertArrayHasKey('limit', $body);
        self::assertArrayHasKey('offset', $body);
    }

    public function testPaginationParametersAreRespected(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/events?limit=5&offset=0');
        $body = json_decode($client->getResponse()->getContent(), true);

        // Assert
        self::assertSame(5, $body['limit']);
        self::assertSame(0, $body['offset']);
        self::assertLessThanOrEqual(5, count($body['items']));
    }

    public function testDetailReturns404ForUnknownEvent(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/events/9999999');

        // Assert
        self::assertResponseStatusCodeSame(404);
    }
}
