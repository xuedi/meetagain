<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Films\Service\OmdbLookup;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Stringable;

class OmdbLookupTest extends TestCase
{
    private const string API_KEY = 'omdb-secret-key';

    public function testASearchFailureIsLoggedWithoutTheApiKey(): void
    {
        // Arrange
        $logger = $this->recordingLogger();
        $lookup = new OmdbLookup($this->failingClient(), $logger, self::API_KEY);

        // Act
        $results = $lookup->searchByTitle('dune', null, 'en');

        // Assert
        static::assertSame([], $results);
        static::assertStringNotContainsString(self::API_KEY, $logger->messages[0]);
        static::assertStringContainsString('apikey=***', $logger->messages[0]);
    }

    public function testAFetchFailureIsLoggedWithoutTheApiKey(): void
    {
        // Arrange
        $logger = $this->recordingLogger();
        $lookup = new OmdbLookup($this->failingClient(), $logger, self::API_KEY);

        // Act
        $film = $lookup->fetchById('tt0000001', 'en');

        // Assert
        static::assertNull($film);
        static::assertStringNotContainsString(self::API_KEY, $logger->messages[0]);
        static::assertStringContainsString('apikey=***', $logger->messages[0]);
    }

    private function failingClient(): MockHttpClient
    {
        return new MockHttpClient([new MockResponse([new TransportException(
            'Idle timeout reached for "https://www.omdbapi.com/?s=dune&apikey=' . self::API_KEY . '&type=movie".',
        )])]);
    }

    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            /** @param array<array-key, mixed> $context */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }
}
