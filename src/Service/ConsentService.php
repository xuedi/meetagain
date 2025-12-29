<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class ConsentService
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getShowCookieConsent(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!($session instanceof SessionInterface)) {
            return true;
        }

        return Consent::getBySession($session)->getCookies() === ConsentType::Unknown;
    }

    public function getShowOsm(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!($session instanceof SessionInterface)) {
            return true;
        }

        return Consent::getBySession($session)->getOsm() === ConsentType::Granted;
    }
}
