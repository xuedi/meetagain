<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use DateTime;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class LocaleCookieService
{
    public const string COOKIE_NAME = 'ma_locale';

    public function __construct(
        private LanguageService $languageService,
    ) {}

    public function createCookie(string $locale): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: $locale,
            expire: new DateTime('+6 months'),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    public function isConsentGranted(Request $request): bool
    {
        return $request->cookies->get(Consent::TYPE_COOKIES) === ConsentType::Granted->value;
    }

    public function getValidLocale(Request $request): ?string
    {
        $locale = $request->cookies->get(self::COOKIE_NAME);
        if ($locale === null || !in_array($locale, $this->languageService->getEnabledCodes(), true)) {
            return null;
        }

        return $locale;
    }

    public function attachIfConsentGranted(Request $request, Response $response, string $locale): void
    {
        if (!$this->isConsentGranted($request)) {
            return;
        }
        if (!in_array($locale, $this->languageService->getEnabledCodes(), true)) {
            return;
        }
        if ($request->cookies->get(self::COOKIE_NAME) === $locale) {
            return;
        }

        $response->headers->setCookie($this->createCookie($locale));
    }
}
