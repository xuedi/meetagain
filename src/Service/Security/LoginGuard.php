<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Form\HumanCheckType;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

readonly class LoginGuard
{
    public const string CONTEXT = 'app_login';
    public const string FORM_NAME = 'meta';
    private const string ANNOUNCEMENT_KEY = 'all';
    private const int IPV6_HOST_BYTES = 8;

    public function __construct(
        #[Target('login_failure')]
        private RateLimiterFactoryInterface $loginFailureLimiter,
        #[Target('login_measures_announcement')]
        private RateLimiterFactoryInterface $announcementLimiter,
        private FormFactoryInterface $formFactory,
    ) {}

    public function createMeasuresForm(): FormInterface
    {
        return $this->formFactory->createNamed(self::FORM_NAME, HumanCheckType::class, null, [
            'context' => self::CONTEXT,
            'csrf_protection' => false,
        ]);
    }

    public function isActive(Request $request): bool
    {
        return $this->limiter($request)->consume(0)->getRemainingTokens() === 0;
    }

    public function recordFailure(Request $request): bool
    {
        $limit = $this->limiter($request)->consume();

        return $limit->isAccepted() && $limit->getRemainingTokens() === 0;
    }

    public function mayAnnounceActivation(): bool
    {
        return $this->announcementLimiter->create(self::ANNOUNCEMENT_KEY)->consume()->isAccepted();
    }

    public function threshold(Request $request): int
    {
        return $this->limiter($request)->consume(0)->getLimit();
    }

    public function reset(Request $request): void
    {
        $this->limiter($request)->reset();
    }

    private function limiter(Request $request): LimiterInterface
    {
        $ip = $request->getClientIp();

        return $this->loginFailureLimiter->create($ip === null ? 'unknown' : IpUtils::anonymize($ip, 0, self::IPV6_HOST_BYTES));
    }
}
