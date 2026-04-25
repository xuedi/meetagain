<?php declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the logs API endpoints.
 *
 * All endpoints require ROLE_ADMIN with Bearer token auth.
 */
class LogsApiControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    /**
     * @return array{0: KernelBrowser, 1: string}
     */
    private function getAdminToken(): array
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]));

        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        return [$client, $token];
    }

    public function testSummaryRequiresAuth(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/logs');

        // Assert
        self::assertResponseStatusCodeSame(401);
    }

    public function testSummaryReturnsAllFourStreams(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        foreach (['system', 'activity', 'notFound', 'cron'] as $key) {
            self::assertArrayHasKey($key, $body, "missing summary key: {$key}");
            self::assertArrayHasKey('count', $body[$key]);
            self::assertArrayHasKey('latest', $body[$key]);
            self::assertIsInt($body[$key]['count']);
        }
    }

    public function testSystemEntriesShape(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs/system?limit=5', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(5, $body['limit']);
        self::assertArrayHasKey('count', $body);
        self::assertArrayHasKey('file', $body);
        self::assertArrayHasKey('entries', $body);
        self::assertLessThanOrEqual(5, count($body['entries']));
    }

    public function testActivityEntriesShape(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs/activity?limit=3', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(3, $body['limit']);
        self::assertArrayHasKey('entries', $body);
        self::assertLessThanOrEqual(3, count($body['entries']));

        if ($body['entries'] !== []) {
            $first = $body['entries'][0];
            foreach (['id', 'createdAt', 'type', 'message', 'meta'] as $field) {
                self::assertArrayHasKey($field, $first);
            }
        }
    }

    public function testNotFoundEntriesShape(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs/not-found?limit=10', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(10, $body['limit']);
        self::assertLessThanOrEqual(10, count($body['entries']));

        if ($body['entries'] !== []) {
            $first = $body['entries'][0];
            foreach (['id', 'createdAt', 'url', 'ip'] as $field) {
                self::assertArrayHasKey($field, $first);
            }
        }
    }

    public function testCronEntriesShape(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs/cron?limit=5', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(5, $body['limit']);

        if ($body['entries'] !== []) {
            $first = $body['entries'][0];
            foreach (['id', 'runAt', 'status', 'durationMs', 'tasks'] as $field) {
                self::assertArrayHasKey($field, $first);
            }
        }
    }

    public function testLimitDefaultsAreApplied(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act: no limit param -> default
        $client->request('GET', '/api/logs/cron', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(200, $body['limit']);
    }

    public function testLimitOverCapReturns400(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act: cron cap is 5000
        $client->request('GET', '/api/logs/cron?limit=99999', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testNonNumericLimitReturns400(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/logs/system?limit=abc', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }
}
