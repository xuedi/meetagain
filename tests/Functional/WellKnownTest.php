<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Repository\NotFoundLogRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WellKnownTest extends WebTestCase
{
    #[DataProvider('servedDocumentProvider')]
    public function testDocumentIsServed(string $path, string $contentType): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', $path);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', $contentType);
    }

    public static function servedDocumentProvider(): iterable
    {
        yield 'security.txt at the RFC 9116 path' => ['/.well-known/security.txt', 'text/plain; charset=utf-8'];
        yield 'security.txt at the legacy root path' => ['/security.txt', 'text/plain; charset=utf-8'];
        yield 'llms.txt at the site root' => ['/llms.txt', 'text/plain; charset=utf-8'];
        yield 'tdmrep.json' => ['/.well-known/tdmrep.json', 'application/json'];
    }

    public function testSecurityTxtCarriesTheRequiredFields(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/.well-known/security.txt');
        $body = (string) $client->getResponse()->getContent();

        // Assert
        self::assertStringContainsString('Contact: ', $body);
        self::assertStringContainsString('Expires: ', $body);
        self::assertStringContainsString('Canonical: ', $body);
    }

    public function testTdmRepReservesRightsByDefault(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/.well-known/tdmrep.json');
        $rules = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Assert
        self::assertSame([['location' => '/', 'tdm-reservation' => 1]], $rules);
    }

    public function testChangePasswordRedirectsToTheProfilePage(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/.well-known/change-password');

        // Assert
        $this->assertResponseRedirects();
        self::assertStringContainsString('/profile/', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testLlmsTxtStartsWithTheSiteHeading(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/llms.txt');
        $body = (string) $client->getResponse()->getContent();

        // Assert
        self::assertStringStartsWith('# ', $body);
        self::assertStringContainsString('## Events', $body);
    }

    public function testRobotsTxtAllowsCrawlingButReservesTraining(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/robots.txt');
        $body = (string) $client->getResponse()->getContent();

        // Assert
        self::assertStringContainsString('User-agent: *', $body);
        self::assertStringContainsString('Content-Usage: train-ai=n', $body);
        self::assertStringNotContainsString('Disallow: /' . "\n", $body);
    }

    #[DataProvider('unclaimedPathProvider')]
    public function testUnclaimedPathIsNotFound(string $path): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', $path);

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public static function unclaimedPathProvider(): iterable
    {
        yield 'A2A agent card we do not publish' => ['/.well-known/agent-card.json'];
        yield 'legacy A2A path' => ['/.well-known/agent.json'];
        yield 'MCP discovery draft' => ['/.well-known/mcp/server-card.json'];
        yield 'bare scanner probe' => ['/.well-known/mcp'];
    }

    public function testUnclaimedPathStillFeedsNotFoundDetection(): void
    {
        // Arrange
        $client = static::createClient();
        $repository = static::getContainer()->get(NotFoundLogRepository::class);
        $before = $repository->count([]);

        // Act
        $client->request('GET', '/.well-known/agents.json');

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertGreaterThan($before, $repository->count([]));
    }
}
