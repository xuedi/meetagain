<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\UrlProbingIncident;
use PHPUnit\Framework\TestCase;

class UrlProbingIncidentTest extends TestCase
{
    public function testSetUserAgentTruncatesTo512(): void
    {
        // Arrange
        $incident = new UrlProbingIncident();

        // Act
        $incident->setUserAgent(str_repeat('u', 1000));

        // Assert
        static::assertSame(512, mb_strlen((string) $incident->getUserAgent()));
    }

    public function testSampleUrlsRoundTrip(): void
    {
        // Arrange
        $incident = new UrlProbingIncident();
        $urls = ['/a', '/b', '/c'];

        // Act
        $incident->setSampleUrls($urls);

        // Assert
        static::assertSame($urls, $incident->getSampleUrls());
    }
}
