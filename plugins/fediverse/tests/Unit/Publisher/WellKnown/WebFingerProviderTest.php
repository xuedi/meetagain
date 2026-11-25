<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Publisher\WellKnown;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Publisher\WellKnown\WebFingerProvider;
use Plugin\Fediverse\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\Request;

class WebFingerProviderTest extends TestCase
{
    /** @return Generator<string, array{string}> */
    public static function resources(): Generator
    {
        yield 'an acct handle' => ['acct:chess_club@events.example'];
        yield 'an acct handle in capitals' => ['ACCT:Chess_Club@Events.Example'];
        yield 'an acct handle with a leading at sign' => ['acct:@chess_club@events.example'];
        yield 'the actor url' => ['https://events.example/fediverse/@chess_club'];
    }

    #[DataProvider('resources')]
    public function testALiveAccountIsFound(string $resource): void
    {
        // Act
        $document = $this->provider($this->account())->provide($this->request('events.example', $resource));

        // Assert
        static::assertNotNull($document);
        $jrd = json_decode($document->body, true);
        static::assertSame('acct:chess_club@events.example', $jrd['subject']);
        static::assertSame(
            ['rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://events.example/fediverse/@chess_club'],
            $jrd['links'][0],
        );
        static::assertSame('application/jrd+json; charset=utf-8', $document->contentType);
    }

    /** @return Generator<string, array{string, string}> */
    public static function misses(): Generator
    {
        yield 'another host in the handle' => ['events.example', 'acct:chess_club@chess.example'];
        yield 'asked on another host' => ['chess.example', 'acct:chess_club@events.example'];
        yield 'no resource' => ['events.example', ''];
        yield 'a foreign url' => ['events.example', 'https://elsewhere.example/fediverse/@chess_club'];
    }

    #[DataProvider('misses')]
    public function testAnythingElseStaysA404(string $host, string $resource): void
    {
        // Act
        $document = $this->provider($this->account())->provide($this->request($host, $resource));

        // Assert
        static::assertNull($document);
    }

    public function testARetiredAccountIsNotFound(): void
    {
        // Arrange
        $account = $this->account()->retire(new DateTimeImmutable('2026-11-20'));

        // Act
        $document = $this->provider($account)->provide($this->request('events.example', 'acct:chess_club@events.example'));

        // Assert
        static::assertNull($document);
    }

    private function account(): Account
    {
        return new Account('7', 'chess_club', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));
    }

    private function request(string $host, string $resource): Request
    {
        return Request::create('https://' . $host . '/.well-known/webfinger', 'GET', ['resource' => $resource]);
    }

    private function provider(Account $account): WebFingerProvider
    {
        $repository = $this->createStub(AccountRepository::class);
        $repository->method('findOneByUsername')->willReturnCallback(static fn(string $username): ?Account => $username === 'chess_club' ? $account : null);

        $actor = $this->createStub(Actor::class);
        $actor->method('accountHost')->willReturn('events.example');
        $actor->method('accountOrigin')->willReturn('https://events.example');
        $actor->method('id')->willReturn('https://events.example/fediverse/@chess_club');
        $actor->method('usernameOf')->willReturnCallback(static fn(string $actorId): ?string => $actorId === 'https://events.example/fediverse/@chess_club'
            ? 'chess_club'
            : null);
        $actor->method('handle')->willReturn('chess_club@events.example');
        $actor->method('profileUrl')->willReturn('https://chess.example/');

        return new WebFingerProvider($repository, $actor);
    }
}
