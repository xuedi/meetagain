<?php declare(strict_types=1);

namespace App\Service\Http;

use App\Service\Config\ConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class RequestHostResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private ConfigService $config,
    ) {}

    /**
     * Scheme + host of the current HTTP request (e.g. "https://example.com").
     * Falls back to ConfigService::getHost() when no request is active (CLI, cron).
     */
    public function getSchemeAndHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim($this->config->getHost(), '/');
    }

    /**
     * Bare host of the current HTTP request (e.g. "example.com"), used for human-readable
     * "you signed up for X" copy. Falls back to ConfigService::getUrl() when no request is active.
     */
    public function getHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getHost();
        }

        return $this->config->getUrl();
    }
}
