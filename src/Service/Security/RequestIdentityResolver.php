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

    public function resolveSessionKey(Request $request, string $ip): string
    {
        $cookieValue = $this->readSessionCookie($request);
        if ($cookieValue !== null) {
            return $cookieValue;
        }

        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
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
        return is_string($value) && $value !== '' ? $value : null;
    }
}
