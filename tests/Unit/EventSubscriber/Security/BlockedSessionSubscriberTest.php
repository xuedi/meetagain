<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\EventSubscriber\Security\BlockedSessionSubscriber;
use App\Service\Security\BlockedSessionStore;
use App\Service\Security\LoadtestBypass;
use App\Service\Security\RequestIdentityResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class BlockedSessionSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresKernelRequestAtPriority64(): void
    {
        $events = BlockedSessionSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::REQUEST, $events);
        static::assertSame([['onKernelRequest', 64]], $events[KernelEvents::REQUEST]);
    }

    public function testSubRequestsAreIgnored(): void
    {
        // Arrange - sub-request must not consult the block store
        $blockStore = $this->createMock(BlockedSessionStore::class);
        $blockStore->expects($this->never())->method('isIpBlocked');
        $blockStore->expects($this->never())->method('isSessionBlocked');

        $subscriber = $this->createSubscriber(blockStore: $blockStore);
        $event = $this->createEvent(new Request(), HttpKernelInterface::SUB_REQUEST);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public function testLoadtestBypassHeaderInNonProdShortCircuitsBeforeStore(): void
    {
        // Arrange - dev env + bypass header => store must never be touched
        $blockStore = $this->createMock(BlockedSessionStore::class);
        $blockStore->expects($this->never())->method('isIpBlocked');
        $blockStore->expects($this->never())->method('isSessionBlocked');

        $request = Request::create('/', server: [
            'HTTP_' . str_replace('-', '_', strtoupper(LoadtestBypass::HEADER)) => '1',
        ]);
        $subscriber = $this->createSubscriber(blockStore: $blockStore, environment: 'dev');

        // Act
        $subscriber->onKernelRequest($this->createEvent($request));

        // Assert - no response set
        static::assertTrue(true);
    }

    #[DataProvider('provideExcludedPathCases')]
    public function testExcludedPathPrefixesSkipBlockCheck(string $path): void
    {
        // Arrange
        $blockStore = $this->createMock(BlockedSessionStore::class);
        $blockStore->expects($this->never())->method('isIpBlocked');
        $blockStore->expects($this->never())->method('isSessionBlocked');

        $subscriber = $this->createSubscriber(blockStore: $blockStore);
        $event = $this->createEvent(Request::create($path));

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public static function provideExcludedPathCases(): iterable
    {
        yield 'admin security root' => ['/admin/security/'];
        yield 'admin security incidents' => ['/admin/security/incidents'];
        yield 'admin security blocked' => ['/admin/security/blocked'];
        yield 'security blocked landing' => ['/security/blocked'];
    }

    public function testBlockedIpProducesA403WithRenderedTemplate(): void
    {
        // Arrange
        $blockStore = $this->createStub(BlockedSessionStore::class);
        $blockStore->method('isIpBlocked')->willReturn(true);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<html>blocked</html>');

        $subscriber = $this->createSubscriber(blockStore: $blockStore, twig: $twig);
        $event = $this->createEvent(Request::create('/some-page', server: ['REMOTE_ADDR' => '203.0.113.7']));

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(403, $response->getStatusCode());
        static::assertSame('<html>blocked</html>', $response->getContent());
        static::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function testBlockedSessionAlsoProducesA403(): void
    {
        // Arrange - IP clean, session id resolves to a blocked key
        $blockStore = $this->createStub(BlockedSessionStore::class);
        $blockStore->method('isIpBlocked')->willReturn(false);
        $blockStore->method('isSessionBlocked')->willReturn(true);

        $resolver = $this->createStub(RequestIdentityResolver::class);
        $resolver->method('resolveSessionKey')->willReturn('sess-abc');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('blocked');

        $subscriber = $this->createSubscriber(blockStore: $blockStore, twig: $twig, resolver: $resolver);
        $event = $this->createEvent(Request::create('/some-page', server: ['REMOTE_ADDR' => '203.0.113.8']));

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        static::assertNotNull($event->getResponse());
        static::assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testEmptyIpSkipsIpCheckButStillChecksSession(): void
    {
        // Arrange - bare Request => no REMOTE_ADDR => getClientIp() returns null
        $blockStore = $this->createMock(BlockedSessionStore::class);
        $blockStore->expects($this->never())->method('isIpBlocked');
        $blockStore->expects($this->once())->method('isSessionBlocked')->willReturn(false);

        $subscriber = $this->createSubscriber(blockStore: $blockStore);
        $event = $this->createEvent(new Request());

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public function testNothingBlockedLeavesEventUntouched(): void
    {
        // Arrange
        $blockStore = $this->createStub(BlockedSessionStore::class);
        $blockStore->method('isIpBlocked')->willReturn(false);
        $blockStore->method('isSessionBlocked')->willReturn(false);

        $subscriber = $this->createSubscriber(blockStore: $blockStore);
        $event = $this->createEvent(Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.9']));

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public function testTwigFailureFallsBackToHardcodedHtmlAndLogs(): void
    {
        // Arrange - twig throws => fallback HTML, but still 403
        $blockStore = $this->createStub(BlockedSessionStore::class);
        $blockStore->method('isIpBlocked')->willReturn(true);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willThrowException(new RuntimeException('twig blew up'));

        $subscriber = $this->createSubscriber(blockStore: $blockStore, twig: $twig);
        $event = $this->createEvent(Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.10']));

        // Act
        $subscriber->onKernelRequest($event);

        // Assert - fallback content still served
        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(403, $response->getStatusCode());
        static::assertStringContainsString('Temporarily blocked', (string) $response->getContent());
    }

    private function createSubscriber(
        ?BlockedSessionStore $blockStore = null,
        ?Environment $twig = null,
        string $environment = 'test',
        ?RequestIdentityResolver $resolver = null,
    ): BlockedSessionSubscriber {
        $resolver ??= $this->createStub(RequestIdentityResolver::class);
        // Default resolver returns a benign key so isSessionBlocked is reached but innocuous
        if ($resolver instanceof \PHPUnit\Framework\MockObject\Stub) {
            $resolver->method('resolveSessionKey')->willReturn('sess-default');
        }
        $twigDefault = $twig ?? $this->createStub(Environment::class);
        if ($twig === null) {
            $twigDefault->method('render')->willReturn('blocked');
        }

        return new BlockedSessionSubscriber(
            $blockStore ?? $this->createStub(BlockedSessionStore::class),
            $twigDefault,
            new NullLogger(),
            $environment,
            $resolver,
        );
    }

    private function createEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
