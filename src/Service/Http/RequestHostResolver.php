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

    public function getSchemeAndHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim($this->config->getHost(), '/');
    }

    public function getHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getHost();
        }

        return $this->config->getUrl();
    }
}
