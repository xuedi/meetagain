<?php declare(strict_types=1);

namespace App\Service\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

readonly class RequestIdentityResolver
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    private const string SESSION_ID_PATTERN = '/^[A-Za-z0-9,-]{22,250}$/';

    public function resolveSessionKey(Request $request, string $ip): string
    {
        $cookieValue = $this->readSessionCookie($request);
        if ($cookieValue !== null) {
            return 's:' . hash('sha256', $cookieValue);
        }

        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
    }

    public function isCrossSite(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        $foreignOrigin = $origin !== null && $origin !== '' && !hash_equals($request->getSchemeAndHttpHost(), $origin);
        $foreignFetch = $fetchSite !== null && $fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true);

        return $foreignOrigin || $foreignFetch;
    }

    private function readSessionCookie(Request $request): ?string
    {
        try {
            $cookieName = $request->getSession()->getName();
        } catch (Throwable $e) {
            $this->logger->debug('Session name read failed in RequestIdentityResolver: ' . $e->getMessage());
            return null;
        }

        $value = $request->cookies->get($cookieName);
        return is_string($value) && preg_match(self::SESSION_ID_PATTERN, $value) === 1 ? $value : null;
    }
}
