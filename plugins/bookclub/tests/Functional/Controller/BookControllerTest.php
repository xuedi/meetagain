<?php declare(strict_types=1);

namespace Plugin\Bookclub\Tests\Functional\Controller;

use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    private const int MAX_QUERIES_INDEX = 70;

    public function testIndexRouteStaysUnderQueryThreshold(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/bookclub');

        // Assert
        static::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        static::assertNotFalse($profile, 'Profiler must be enabled in test env to run this regression guard.');

        $collector = $profile->getCollector('db');
        static::assertInstanceOf(DoctrineDataCollector::class, $collector);

        static::assertLessThan(
            self::MAX_QUERIES_INDEX,
            $collector->getQueryCount(),
            sprintf(
                '/en/bookclub fired %d queries (threshold %d). The fetch-join in BookRepository::findApproved() may have regressed.',
                $collector->getQueryCount(),
                self::MAX_QUERIES_INDEX,
            ),
        );
    }
}
