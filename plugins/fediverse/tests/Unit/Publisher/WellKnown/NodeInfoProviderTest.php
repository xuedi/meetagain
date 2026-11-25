<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Publisher\WellKnown;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Controller\NodeInfoController;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Publisher\WellKnown\NodeInfoProvider;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Service\AccountService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NodeInfoProviderTest extends TestCase
{
    private const string ACTOR_ID = 'https://events.example/fediverse/@events';

    public function testAFederatingInstallationAccountIsTheApplicationActor(): void
    {
        // Act
        $links = $this->links($this->installation(), isFederating: true);

        // Assert
        static::assertSame(
            [
                ['rel' => NodeInfoController::SCHEMA, 'href' => 'https://events.example/fediverse/nodeinfo/2.1'],
                ['rel' => NodeInfoProvider::APPLICATION_REL, 'href' => self::ACTOR_ID],
            ],
            $links,
        );
    }

    public function testAnInstallationAccountThatStoppedFederatingIsNotNamed(): void
    {
        // Act
        $links = $this->links($this->installation(), isFederating: false);

        // Assert
        static::assertSame([['rel' => NodeInfoController::SCHEMA, 'href' => 'https://events.example/fediverse/nodeinfo/2.1']], $links);
    }

    public function testNoInstallationAccountNamesNoApplication(): void
    {
        // Act
        $links = $this->links(null, isFederating: true);

        // Assert
        static::assertCount(1, $links);
    }

    public function testAnotherHostIsDeclined(): void
    {
        // Act
        $document = $this->provider($this->installation(), true)->provide(Request::create('https://chess.example/.well-known/nodeinfo'));

        // Assert
        static::assertNull($document);
    }

    private function installation(): Account
    {
        return new Account(null, 'events', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));
    }

    /** @return list<array{rel: string, href: string}> */
    private function links(?Account $installation, bool $isFederating): array
    {
        $document = $this->provider($installation, $isFederating)->provide(Request::create('https://events.example/.well-known/nodeinfo'));
        static::assertNotNull($document);

        return json_decode($document->body, true, 512, JSON_THROW_ON_ERROR)['links'];
    }

    private function provider(?Account $installation, bool $isFederating): NodeInfoProvider
    {
        $actor = $this->createStub(Actor::class);
        $actor->method('accountHost')->willReturn('events.example');
        $actor->method('accountOrigin')->willReturn('https://events.example');
        $actor->method('id')->willReturn(self::ACTOR_ID);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/fediverse/nodeinfo/2.1');

        $accounts = $this->createStub(AccountRepository::class);
        $accounts->method('findLiveForScope')->willReturn($installation);

        $accountService = $this->createStub(AccountService::class);
        $accountService->method('isFederating')->willReturn($isFederating);

        return new NodeInfoProvider($actor, $urlGenerator, $accounts, $accountService);
    }
}
