<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\LoginGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class LoginGuardTest extends TestCase
{
    public function testTheGuardStaysOffUntilTheThirdFailure(): void
    {
        // Arrange
        $guard = $this->guard();
        $request = $this->request('10.0.0.1');

        // Act
        $first = $guard->recordFailure($request);
        $second = $guard->recordFailure($request);
        $activeBeforeThird = $guard->isActive($request);
        $third = $guard->recordFailure($request);

        // Assert
        static::assertFalse($first);
        static::assertFalse($second);
        static::assertFalse($activeBeforeThird);
        static::assertTrue($third);
        static::assertTrue($guard->isActive($request));
    }

    public function testOnlyTheCrossingFailureReportsTheActivation(): void
    {
        // Arrange
        $guard = $this->guard();
        $request = $this->request('10.0.0.1');
        $guard->recordFailure($request);
        $guard->recordFailure($request);
        $guard->recordFailure($request);

        // Act
        $fourth = $guard->recordFailure($request);
        $fifth = $guard->recordFailure($request);

        // Assert
        static::assertFalse($fourth);
        static::assertFalse($fifth);
    }

    public function testFailuresAreCountedPerIp(): void
    {
        // Arrange
        $guard = $this->guard();
        $attacker = $this->request('10.0.0.1');
        for ($i = 0; $i < 3; ++$i) {
            $guard->recordFailure($attacker);
        }

        // Act
        $otherVisitorActive = $guard->isActive($this->request('10.0.0.2'));

        // Assert
        static::assertFalse($otherVisitorActive);
    }

    public function testResetSwitchesTheGuardOff(): void
    {
        // Arrange
        $guard = $this->guard();
        $request = $this->request('10.0.0.1');
        for ($i = 0; $i < 3; ++$i) {
            $guard->recordFailure($request);
        }

        // Act
        $guard->reset($request);

        // Assert
        static::assertFalse($guard->isActive($request));
        static::assertSame(3, $guard->threshold($request));
    }

    public function testAllAddressesOfOneIpv6NetworkShareOneCounter(): void
    {
        // Arrange
        $guard = $this->guard();
        $guard->recordFailure($this->request('2001:db8:1:2::1'));
        $guard->recordFailure($this->request('2001:db8:1:2::2'));

        // Act
        $crossed = $guard->recordFailure($this->request('2001:db8:1:2:ffff::3'));

        // Assert
        static::assertTrue($crossed);
        static::assertFalse($guard->isActive($this->request('2001:db8:1:3::1')));
    }

    public function testAnnouncementsStopAtTheGlobalCap(): void
    {
        // Arrange
        $guard = $this->guard(announcementLimit: 2);

        // Act
        $first = $guard->mayAnnounceActivation();
        $second = $guard->mayAnnounceActivation();
        $third = $guard->mayAnnounceActivation();

        // Assert
        static::assertTrue($first);
        static::assertTrue($second);
        static::assertFalse($third);
    }

    private function guard(int $announcementLimit = 30): LoginGuard
    {
        $failures = new RateLimiterFactory([
            'id' => 'login_failure',
            'policy' => 'sliding_window',
            'limit' => 3,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
        $announcements = new RateLimiterFactory([
            'id' => 'login_measures_announcement',
            'policy' => 'fixed_window',
            'limit' => $announcementLimit,
            'interval' => '1 hour',
        ], new InMemoryStorage());

        return new LoginGuard($failures, $announcements, $this->createStub(FormFactoryInterface::class));
    }

    private function request(string $ip): Request
    {
        return Request::create('/login', 'POST', server: ['REMOTE_ADDR' => $ip]);
    }
}
