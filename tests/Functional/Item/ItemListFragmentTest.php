<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ItemListFragmentTest extends WebTestCase
{
    private const string GLOSSARY_HOST = 'dragon.meetagain.local';

    public function testXhrCallReturnsTheFilterBoxTheListBodyAndTheCleanUrl(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/fragment', server: $this->xhr());

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertSame(['filter', 'body', 'url'], array_keys($payload));
        static::assertStringContainsString('data-item-facet', $payload['filter']);
        static::assertStringContainsString('item-result-header', $payload['body']);
        static::assertSame('/en/glossary', $payload['url']);
    }

    public function testFacetQueryIsCarriedIntoTheReturnedUrl(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/fragment?category=1', server: $this->xhr());

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertSame('/en/glossary?category=1', $payload['url']);
    }

    public function testDirectHitRedirectsToTheListPage(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/fragment', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        $this->assertResponseRedirects('/en/glossary');
    }

    public function testUnknownItemTypeIsNotFound(): void
    {
        // Arrange
        $client = static::createClient();
        $client->catchExceptions(true);

        // Act
        $client->request('GET', '/en/item/nonsense/fragment', server: $this->xhr());

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTypeOfAnInactivePluginIsNotFound(): void
    {
        // Arrange
        $client = static::createClient();
        $client->catchExceptions(true);

        // Act
        $client->request('GET', '/en/item/glossary/fragment', server: [
            'HTTP_HOST' => 'cinema.meetagain.local',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** @return array<string, string> */
    private function xhr(): array
    {
        return [
            'HTTP_HOST' => self::GLOSSARY_HOST,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
    }
}
