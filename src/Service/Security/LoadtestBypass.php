<?php declare(strict_types=1);

namespace App\Service\Security;

use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;

/**
 * Single file housing all load-test bypass plumbing:
 *
 *   - {@see isActive()}        - the central decision (env + header check) used
 *                                 by SecurityService and BlockedSessionSubscriber
 *   - {@see RateLimiterFactory} - decorator wrapping form rate limiters
 *                                 (limiter.password_reset, .registration, .support)
 *   - {@see RequestRateLimiter} - decorator wrapping login_throttling
 *                                 (security.login_throttling.main.limiter)
 *
 * The two decorator classes are inner so they can share the `isActive` check
 * and the `accepted()` helper without anything else needing to know they
 * exist. They are wired via services.yaml.
 *
 * Bypass triggers when:
 *   - APP_ENV !== 'prod'
 *   - request carries `X-Loadtest-Bypass: 1`
 *
 * Both conditions must hold. In prod the header is silently ignored.
 */
final class LoadtestBypass
{
    public const string HEADER = 'X-Loadtest-Bypass';

    public static function isActive(?Request $request, string $environment): bool
    {
        if ($environment === 'prod') {
            return false;
        }
        if ($request === null) {
            return false;
        }
        return $request->headers->get(self::HEADER) === '1';
    }

    public static function accepted(): RateLimit
    {
        return new RateLimit(availableTokens: PHP_INT_MAX, retryAfter: new DateTimeImmutable('@0'), accepted: true, limit: PHP_INT_MAX);
    }
}

/**
 * Decorator for form-style rate limiters (limiter.password_reset, etc.).
 * Returns a no-op LimiterInterface when bypass is active.
 */
final readonly class LoadtestBypassRateLimiterFactory implements RateLimiterFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private RateLimiterFactoryInterface $inner,
        private RequestStack $requestStack,
        private string $environment,
    ) {}

    #[Override]
    public function create(?string $key = null): LimiterInterface
    {
        if (LoadtestBypass::isActive($this->requestStack->getMainRequest(), $this->environment)) {
            return new class implements LimiterInterface {
                #[Override]
                public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
                {
                    return new Reservation(0.0, LoadtestBypass::accepted());
                }

                #[Override]
                public function consume(int $tokens = 1): RateLimit
                {
                    return LoadtestBypass::accepted();
                }

                #[Override]
                public function reset(): void {}
            };
        }

        return $this->inner->create($key);
    }
}

/**
 * Decorator for the request-bound login_throttling limiter
 * (security.login_throttling.main.limiter). Returns an accepted RateLimit
 * directly when bypass is active so login_throttling never trips for
 * synthetic load traffic.
 */
final readonly class LoadtestBypassRequestRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        #[AutowireDecorated]
        private RequestRateLimiterInterface $inner,
        private string $environment,
    ) {}

    #[Override]
    public function consume(Request $request): RateLimit
    {
        if (LoadtestBypass::isActive($request, $this->environment)) {
            return LoadtestBypass::accepted();
        }

        return $this->inner->consume($request);
    }

    #[Override]
    public function reset(Request $request): void
    {
        $this->inner->reset($request);
    }
}
