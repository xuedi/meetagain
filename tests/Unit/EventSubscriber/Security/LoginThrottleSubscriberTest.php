<?php

declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\EventSubscriber\Security\LoginThrottleSubscriber;
use App\Service\Security\RateLimitLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginThrottleSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsListensToLoginFailureEvent(): void
    {
        // Arrange + Act
        $events = LoginThrottleSubscriber::getSubscribedEvents();

        // Assert
        static::assertArrayHasKey(LoginFailureEvent::class, $events);
        static::assertSame('onLoginFailure', $events[LoginFailureEvent::class]);
    }

    public function testIgnoresFailuresThatAreNotThrottle(): void
    {
        // Arrange
        $logger = $this->createMock(RateLimitLogger::class);
        $logger->expects($this->never())->method('log');
        $subscriber = new LoginThrottleSubscriber($logger);

        $event = $this->buildFailureEvent(new AuthenticationException('bad credentials'));

        // Act
        $subscriber->onLoginFailure($event);

        // Assert: nothing to assert beyond the mock expectation.
        static::assertTrue(true);
    }

    public function testLogsThrottleFailureWithoutPassport(): void
    {
        // Arrange
        $logger = $this->createMock(RateLimitLogger::class);
        $logger
            ->expects($this->once())
            ->method('log')
            ->with('login_throttling', static::isInstanceOf(Request::class), null);
        $subscriber = new LoginThrottleSubscriber($logger);

        $event = $this->buildFailureEvent(new TooManyLoginAttemptsAuthenticationException(), passport: null);

        // Act
        $subscriber->onLoginFailure($event);

        // Assert
        static::assertTrue(true);
    }

    public function testLogsThrottleFailureWithPassportIdentifier(): void
    {
        // Arrange
        $logger = $this->createMock(RateLimitLogger::class);
        $logger
            ->expects($this->once())
            ->method('log')
            ->with('login_throttling', static::isInstanceOf(Request::class), 'attacker@example.org');
        $subscriber = new LoginThrottleSubscriber($logger);

        $passport = new SelfValidatingPassport(new UserBadge('attacker@example.org'));
        $event = $this->buildFailureEvent(new TooManyLoginAttemptsAuthenticationException(), passport: $passport);

        // Act
        $subscriber->onLoginFailure($event);

        // Assert
        static::assertTrue(true);
    }

    private function buildFailureEvent(
        AuthenticationException $exception,
        ?Passport $passport = null,
    ): LoginFailureEvent {
        $request = Request::create('/login', 'POST');
        $response = new Response();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $authenticator = $this->createStub(AuthenticatorInterface::class);

        return new LoginFailureEvent($exception, $authenticator, $request, $response, 'main', $passport);
    }
}
