<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Entity\Language;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LanguageTileService
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return array{greeting: string, intro: string, cta: string, alt: string}
     */
    public function getText(Language $language): array
    {
        $code = $language->getCode();
        $name = $language->getName();

        return [
            'greeting' => $this->resolve($language->getTileGreeting(), 'greeting_' . $code, $code, $name),
            'intro' => $this->resolve($language->getTileIntro(), 'intro_' . $code, $code, ''),
            'cta' => $this->resolve($language->getTileCta(), 'cta_continue_' . $code, $code, $name),
            'alt' => $this->resolve($language->getTileImageAlt(), 'tile_alt_' . $code, $code, $name, ['%language%' => $name]),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function resolve(?string $stored, string $keySuffix, string $code, string $fallback, array $params = []): string
    {
        $custom = trim($stored ?? '');
        if ($custom !== '') {
            return strtr($custom, $params);
        }

        $key = 'language_tile.' . $keySuffix;
        $translated = $this->translator->trans($key, $params, 'messages', $code);

        return $translated === $key ? $fallback : $translated;
    }
}
