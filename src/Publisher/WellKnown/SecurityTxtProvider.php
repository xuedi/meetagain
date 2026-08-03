<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SecurityTxtProvider implements WellKnownProviderInterface
{
    public const string SUFFIX = 'security.txt';

    private const int MAX_AGE = 86400;

    public function __construct(
        private ConfigService $configService,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return self::SUFFIX;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function provide(Request $request): ?WellKnownDocument
    {
        $lines = [
            'Contact: ' . $this->resolveContact($request),
            'Expires: ' . new DateTimeImmutable('+1 year')->format(DATE_ATOM),
        ];

        $encryption = $this->configService->getSecurityEncryptionUrl();
        if ($encryption !== '') {
            $lines[] = 'Encryption: ' . $encryption;
        }

        $languages = $this->resolvePreferredLanguages();
        if ($languages !== '') {
            $lines[] = 'Preferred-Languages: ' . $languages;
        }

        $lines[] = 'Canonical: ' . $request->getSchemeAndHttpHost() . '/.well-known/' . self::SUFFIX;

        return WellKnownDocument::of(implode("\n", $lines) . "\n", 'text/plain; charset=utf-8', self::MAX_AGE);
    }

    private function resolveContact(Request $request): string
    {
        $contact = $this->configService->getSecurityContact();
        if ($contact !== '') {
            return $contact;
        }

        return $this->urlGenerator->generate(
            'app_contact',
            ['_locale' => $request->getLocale()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function resolvePreferredLanguages(): string
    {
        $configured = $this->configService->getSecurityPreferredLanguages();
        if ($configured !== '') {
            return $configured;
        }

        return implode(', ', $this->languageService->getEnabledCodes());
    }
}
