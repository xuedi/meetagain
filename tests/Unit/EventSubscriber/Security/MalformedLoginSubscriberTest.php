<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\Controller\SecurityController;
use App\EventSubscriber\Security\MalformedLoginSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MalformedLoginSubscriberTest extends TestCase
{
    public function testItRunsBeforeTheFirewallReachesTheAuthenticator(): void
    {
        // Arrange
        $firewallPriorities = array_map(static fn(array $listener): int => $listener[1], FirewallListener::getSubscribedEvents()[KernelEvents::REQUEST]);

        // Act
        $listeners = MalformedLoginSubscriber::getSubscribedEvents()[KernelEvents::REQUEST];

        // Assert
        static::assertCount(1, $listeners);
        static::assertSame('onKernelRequest', $listeners[0][0]);
        static::assertGreaterThan(max($firewallPriorities), $listeners[0][1]);
    }

    #[DataProvider('malformedLoginProvider')]
    public function testAMalformedLoginIsSentBackToTheLoginPage(array $body): void
    {
        // Arrange
        $event = $this->event('POST', SecurityController::LOGIN_ROUTE, $body);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/login', $response->getTargetUrl());
    }

    public static function malformedLoginProvider(): iterable
    {
        yield 'the username is an array' => [['_username' => ['a', 'b'], '_password' => 'secret']];
        yield 'the username is missing' => [['_password' => 'secret']];
    }

    #[DataProvider('untouchedRequestProvider')]
    public function testEveryOtherRequestIsLeftAlone(string $method, string $route, array $body, int $type): void
    {
        // Arrange
        $event = $this->event($method, $route, $body, $type);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public static function untouchedRequestProvider(): iterable
    {
        $malformed = ['_username' => ['a']];

        yield 'a well-formed login' => ['POST', SecurityController::LOGIN_ROUTE, ['_username' => 'anna@example.org'], HttpKernelInterface::MAIN_REQUEST];
        yield 'showing the login form' => ['GET', SecurityController::LOGIN_ROUTE, [], HttpKernelInterface::MAIN_REQUEST];
        yield 'a post to another route' => ['POST', 'app_register', $malformed, HttpKernelInterface::MAIN_REQUEST];
        yield 'a sub-request' => ['POST', SecurityController::LOGIN_ROUTE, $malformed, HttpKernelInterface::SUB_REQUEST];
    }

    private function subscriber(): MalformedLoginSubscriber
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');

        return new MalformedLoginSubscriber($urlGenerator);
    }

    private function event(string $method, string $route, array $body, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/login', $method, $body);
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
