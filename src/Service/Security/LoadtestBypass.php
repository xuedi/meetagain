<?php declare(strict_types=1);

namespace App\Service\Security;

use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;

final readonly class LoadtestBypass
{
    public const string HEADER = 'X-Loadtest-Bypass';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    // Both conditions must hold; in prod the header is silently ignored
    public function isActive(?Request $request): bool
    {
        if ($this->environment === 'prod') {
            return false;
        }
        if ($request === null) {
            return false;
        }
        return $request->headers->get(self::HEADER) === '1';
    }

    public function accepted(): RateLimit
    {
        return new RateLimit(availableTokens: PHP_INT_MAX, retryAfter: new DateTimeImmutable('@0'), accepted: true, limit: PHP_INT_MAX);
    }
}

final readonly class LoadtestBypassRateLimiterFactory implements RateLimiterFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private RateLimiterFactoryInterface $inner,
        private RequestStack $requestStack,
        private LoadtestBypass $bypass,
    ) {}

    #[Override]
    public function create(?string $key = null): LimiterInterface
    {
        if ($this->bypass->isActive($this->requestStack->getMainRequest())) {
            return new readonly class($this->bypass) implements LimiterInterface {
                public function __construct(
                    private LoadtestBypass $bypass,
                ) {}

                #[Override]
                public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
                {
                    return new Reservation(0.0, $this->bypass->accepted());
                }

                #[Override]
                public function consume(int $tokens = 1): RateLimit
                {
                    return $this->bypass->accepted();
                }

                #[Override]
                public function reset(): void {}
            };
        }

        return $this->inner->create($key);
    }
}

final readonly class LoadtestBypassRequestRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        #[AutowireDecorated]
        private RequestRateLimiterInterface $inner,
        private LoadtestBypass $bypass,
    ) {}

    #[Override]
    public function consume(Request $request): RateLimit
    {
        if ($this->bypass->isActive($request)) {
            return $this->bypass->accepted();
        }

        return $this->inner->consume($request);
    }

    #[Override]
    public function reset(Request $request): void
    {
        $this->inner->reset($request);
    }
}
