<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\Activity\ActivityService;
use App\Activity\Messages\LoginMeasuresActivated;
use App\Enum\SecurityEventType;
use App\EventSubscriber\Security\LoginAttemptSubscriber;
use App\Service\Security\LoginGuard;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginAttemptSubscriberTest extends TestCase
{
    private LoginGuard $guard;
    private bool $measuresFormValid = true;

    protected function setUp(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isValid')->willReturnCallback(fn(): bool => $this->measuresFormValid);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        $this->guard = new LoginGuard(
            new RateLimiterFactory(
                ['id' => 'login_failure', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '15 minutes'],
                new InMemoryStorage(),
            ),
            new RateLimiterFactory(
                ['id' => 'login_measures_announcement', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
                new InMemoryStorage(),
            ),
            $formFactory,
        );
    }

    public function testNonThrottleFailuresAreNotForwardedToTheSecurityService(): void
    {
        // Arrange
        $securityService = $this->createMock(SecurityService::class);
        $securityService->expects($this->never())->method('event');
        $subscriber = $this->subscriber(securityService: $securityService);

        // Act
        $subscriber->onLoginFailure($this->failure(new AuthenticationException('bad credentials')));
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
        $subscriber = $this->subscriber(securityService: $securityService);

        // Act
        $subscriber->onLoginFailure($this->failure(new TooManyLoginAttemptsAuthenticationException()));

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
        $subscriber = $this->subscriber(securityService: $securityService);
        $passport = new SelfValidatingPassport(new UserBadge('alice@example.test'));

        // Act
        $subscriber->onLoginFailure($this->failure(new TooManyLoginAttemptsAuthenticationException(), $passport));

        // Assert
        static::assertSame('alice@example.test', $captured[2]['userIdentifier']);
        static::assertSame('login_throttling', $captured[2]['limiter']);
    }

    public function testABruteForceWritesExactlyOneActivityOnTheThirdFailure(): void
    {
        // Arrange
        $logged = [];
        $activityService = $this->createStub(ActivityService::class);
        $activityService->method('log')->willReturnCallback(static function (...$args) use (&$logged): void {
            $logged[] = $args;
        });
        $subscriber = $this->subscriber(activityService: $activityService);

        // Act
        for ($i = 0; $i < 8; ++$i) {
            $subscriber->onLoginFailure($this->failure(new AuthenticationException('bad credentials'), $this->unknownUserPassport()));
        }

        // Assert
        static::assertCount(1, $logged);
        static::assertSame(LoginMeasuresActivated::TYPE, $logged[0][0]);
        static::assertNull($logged[0][1]);
        static::assertSame(['ip' => '10.0.0.1', 'attempts' => 3], $logged[0][2]);
    }

    public function testActivationsFromOtherAddressesPastTheCapWriteNoActivity(): void
    {
        // Arrange
        $logged = 0;
        $activityService = $this->createStub(ActivityService::class);
        $activityService->method('log')->willReturnCallback(static function () use (&$logged): void {
            $logged++;
        });
        $subscriber = $this->subscriber(activityService: $activityService);

        // Act
        foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
            $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => $ip]);
            $request->attributes->set(LoginAttemptSubscriber::ROUTE_DEFAULT, true);
            for ($i = 0; $i < 3; ++$i) {
                $subscriber->onLoginFailure($this->failure(new AuthenticationException('bad'), null, $request));
            }
        }

        // Assert
        static::assertSame(1, $logged);
        static::assertTrue($this->guard->isActive(Request::create('/login', server: ['REMOTE_ADDR' => '10.0.0.2'])));
    }

    public function testFailuresOutsideTheLoginRouteAreNotCounted(): void
    {
        // Arrange
        $subscriber = $this->subscriber();
        $request = Request::create('/jump', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->attributes->set('_route', 'app_jump_landing');

        // Act
        for ($i = 0; $i < 3; ++$i) {
            $subscriber->onLoginFailure($this->failure(new AuthenticationException('bad'), null, $request));
        }

        // Assert
        static::assertFalse($this->guard->isActive($this->loginRequest()));
    }

    public function testThePassportIsNotCheckedWhileTheGuardIsOff(): void
    {
        // Arrange
        $this->measuresFormValid = false;
        $request = $this->loginRequest();
        $subscriber = $this->subscriber(request: $request);

        // Act
        $subscriber->onCheckPassport($this->passportCheck());

        // Assert
        static::assertFalse($this->guard->isActive($request));
    }

    public function testAFailedMeasureCheckRejectsTheLoginOnceTheGuardIsOn(): void
    {
        // Arrange
        $this->measuresFormValid = false;
        $request = $this->loginRequest();
        for ($i = 0; $i < 3; ++$i) {
            $this->guard->recordFailure($request);
        }
        $subscriber = $this->subscriber(request: $request);

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);

        // Act
        $subscriber->onCheckPassport($this->passportCheck());
    }

    public function testAPassedMeasureCheckLetsTheLoginContinue(): void
    {
        // Arrange
        $this->measuresFormValid = true;
        $request = $this->loginRequest();
        for ($i = 0; $i < 3; ++$i) {
            $this->guard->recordFailure($request);
        }
        $subscriber = $this->subscriber(request: $request);

        // Act
        $subscriber->onCheckPassport($this->passportCheck());

        // Assert
        static::assertTrue($this->guard->isActive($request));
    }

    public function testAnyRouteWithTheLoginAttemptDefaultIsCounted(): void
    {
        // Arrange
        $subscriber = $this->subscriber();
        $request = Request::create('/api/v1/auth/login', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->attributes->set('_route', 'some_other_login');
        $request->attributes->set(LoginAttemptSubscriber::ROUTE_DEFAULT, true);

        // Act
        for ($i = 0; $i < 3; ++$i) {
            $subscriber->onLoginFailure($this->failure(new AuthenticationException('bad'), null, $request));
        }

        // Assert
        static::assertTrue($this->guard->isActive($this->loginRequest()));
    }

    public function testAStatelessLoginIsRefusedWithoutValidatingTheForm(): void
    {
        // Arrange
        $this->measuresFormValid = true;
        $request = $this->loginRequest();
        $request->attributes->set('_stateless', true);
        for ($i = 0; $i < 3; ++$i) {
            $this->guard->recordFailure($request);
        }
        $subscriber = $this->subscriber(request: $request);

        // Assert
        $this->expectExceptionObject(new CustomUserMessageAuthenticationException(LoginAttemptSubscriber::HUMAN_CHECK_FAILED));

        // Act
        $subscriber->onCheckPassport($this->passportCheck());
    }

    private function subscriber(
        ?SecurityService $securityService = null,
        ?ActivityService $activityService = null,
        ?Request $request = null,
    ): LoginAttemptSubscriber {
        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }

        return new LoginAttemptSubscriber(
            $securityService ?? $this->createStub(SecurityService::class),
            $this->guard,
            $activityService ?? $this->createStub(ActivityService::class),
            $requestStack,
        );
    }

    private function loginRequest(): Request
    {
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->attributes->set(LoginAttemptSubscriber::ROUTE_DEFAULT, true);

        return $request;
    }

    private function unknownUserPassport(): Passport
    {
        return new SelfValidatingPassport(new UserBadge('nobody@example.test', static function (): never {
            throw new UserNotFoundException();
        }));
    }

    private function passportCheck(): CheckPassportEvent
    {
        return new CheckPassportEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('alice@example.test')),
        );
    }

    private function failure(AuthenticationException $exception, ?Passport $passport = null, ?Request $request = null): LoginFailureEvent
    {
        return new LoginFailureEvent(
            $exception,
            $this->createStub(AuthenticatorInterface::class),
            $request ?? $this->loginRequest(),
            null,
            'main',
            $passport,
        );
    }
}
