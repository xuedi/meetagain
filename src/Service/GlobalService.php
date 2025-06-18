<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Repository\PluginRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class GlobalService
{
    public function __construct(
        private RequestStack $requestStack,
        private TranslationService $translationService,
        private DashboardService $dashboardService,
        private PluginRepository $pluginRepo,
    )
    {
    }

    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            return $request->getLocale();
        }

        throw new RuntimeException('Cound not get current locale');
    }

    public function getLanguageCodes(): array
    {
        return $this->translationService->getLanguageCodes();
    }

    public function getPlugins(): array
    {
        return $this->pluginRepo->findBy(['enabled' => true], ['name' => 'ASC']);
    }

    public function hasNewMessages(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            return $request->getSession()->get('hasNewMessage', false);
        }

        return false;
    }

    public function getShowCookieConsent(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof SessionInterface) {
            return true;
        }

        return Consent::getBySession($session)->getCookies() === ConsentType::Unknown;
    }

    public function getShowOsm(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof SessionInterface) {
            return true;
        }

        return Consent::getBySession($session)->getOsm() === ConsentType::Granted;
    }

    public function getAdminAttention(): bool
    {
        if (count($this->dashboardService->getNeedForApproval()) > 0) {
            return true;
        }

        return false;
    }

    public function getAlternativeLanguageCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $currentUri = $request->getRequestUri();
            $currentLocale = $request->getLocale();
            if (!str_starts_with($currentUri, '/_profiler')) {
                return $this->translationService->getAltLangList($currentLocale, $currentUri);
            }
        }

        return [];
    }
}
