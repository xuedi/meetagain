<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class ConsentService
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function getShowOsm(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof SessionInterface) {
            return true;
        }

        return Consent::getBySession($session)->getOsm() === ConsentType::Granted;
    }

    public function setShowOsm(bool $granted, ?Response $response = null): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof SessionInterface) {
            return;
        }

        $consent = Consent::getBySession($session);
        $consent->setOsm($granted ? ConsentType::Granted : ConsentType::Denied);
        $consent->save($session);

        if ($response === null) {
            return;
        }

        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}
