<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Publisher\WellKnown;

use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Publisher\WellKnown\HostMetaProvider;
use Symfony\Component\HttpFoundation\Request;

class HostMetaProviderTest extends TestCase
{
    public function testTheTemplatePointsAtWebFingerOnTheAccountHost(): void
    {
        // Act
        $document = $this->provider('https://events.example')->provide(Request::create('https://events.example/.well-known/host-meta'));

        // Assert
        static::assertNotNull($document);
        static::assertSame('application/xrd+xml; charset=utf-8', $document->contentType);
        static::assertStringContainsString(
            '<Link rel="lrdd" type="application/jrd+json" template="https://events.example/.well-known/webfinger?resource={uri}"/>',
            $document->body,
        );
    }

    public function testTheOriginIsEscapedForXml(): void
    {
        // Act
        $document = $this->provider('https://events.example/a&b"c')->provide(Request::create('https://events.example/.well-known/host-meta'));

        // Assert
        static::assertNotNull($document);
        static::assertStringContainsString('template="https://events.example/a&amp;b&quot;c/.well-known/webfinger?resource={uri}"', $document->body);
    }

    public function testAnotherHostIsDeclined(): void
    {
        // Act
        $document = $this->provider('https://events.example')->provide(Request::create('https://chess.example/.well-known/host-meta'));

        // Assert
        static::assertNull($document);
    }

    private function provider(string $origin): HostMetaProvider
    {
        $actor = $this->createStub(Actor::class);
        $actor->method('accountOrigin')->willReturn($origin);
        $actor->method('accountHost')->willReturn('events.example');

        return new HostMetaProvider($actor);
    }
}
