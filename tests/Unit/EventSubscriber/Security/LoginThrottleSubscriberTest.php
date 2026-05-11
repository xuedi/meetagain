<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\Enum\SecurityEventType;
use App\EventSubscriber\Security\LoginThrottleSubscriber;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginThrottleSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresLoginFailureEvent(): void
    {
        $events = LoginThrottleSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(LoginFailureEvent::class, $events);
        static::assertSame('onLoginFailure', $events[LoginFailureEvent::class]);
    }

    public function testNonThrottleExceptionsAreIgnored(): void
    {
        // Arrange
        $securityService = $this->createMock(SecurityService::class);
        $securityService->expects($this->never())->method('event');

        $subscriber = new LoginThrottleSubscriber($securityService);
        $event = $this->makeEvent(new AuthenticationException('bad credentials'), null);

        // Act
        $subscriber->onLoginFailure($event);
    }

    public function testThrottleWithoutPassportStillRecordsWithoutIdentifier(): void
    {
        // Arrange
        $captured = [];
        $securityService = $this->createMock(SecurityService::class);
        $securityService
            ->expects($this->once())
            ->method('event')
            ->willReturnCallback(static function (...$args) use (&$captured): void {
                $captured = $args;
            });

        $subscriber = new LoginThrottleSubscriber($securityService);
        $event = $this->makeEvent(new TooManyLoginAttemptsAuthenticationException(), null);

        // Act
        $subscriber->onLoginFailure($event);

        // Assert
        static::assertSame(SecurityEventType::RateLimit, $captured[0]);
        static::assertSame(['limiter' => 'login_throttling'], $captured[2]);
    }

    public function testThrottleWithUserBadgeIncludesIdentifierInContext(): void
    {
        // Arrange
        $captured = [];
        $securityService = $this->createMock(SecurityService::class);
        $securityService
            ->expects($this->once())
            ->method('event')
            ->willReturnCallback(static function (...$args) use (&$captured): void {
                $captured = $args;
            });

        $subscriber = new LoginThrottleSubscriber($securityService);
        $passport = new SelfValidatingPassport(new UserBadge('alice@example.test'));
        $event = $this->makeEvent(new TooManyLoginAttemptsAuthenticationException(), $passport);

        // Act
        $subscriber->onLoginFailure($event);

        // Assert
        static::assertSame('alice@example.test', $captured[2]['userIdentifier']);
        static::assertSame('login_throttling', $captured[2]['limiter']);
    }

    private function makeEvent(AuthenticationException $exception, ?Passport $passport): LoginFailureEvent
    {
        return new LoginFailureEvent(
            $exception,
            $this->createStub(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            Request::create('/login'),
            null,
            'main',
            $passport,
        );
    }
}
