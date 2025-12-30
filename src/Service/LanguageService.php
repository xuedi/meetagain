<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class LanguageService
{
    private const string CACHE_KEY_ENABLED_CODES = 'language.enabled_codes';
    private const string CACHE_KEY_ALL_LANGUAGES = 'language.all_languages';
    private const int CACHE_TTL = 3600;

    public function __construct(
        private LanguageRepository $languageRepo,
        private TagAwareCacheInterface $appCache,
    ) {
    }

    /**
     * @return string[] Array of enabled language codes ordered by sortOrder
     */
    public function getEnabledCodes(): array
    {
        try {
            return $this->appCache->get(
                self::CACHE_KEY_ENABLED_CODES,
                function (ItemInterface $item): array {
                    $item->expiresAfter(self::CACHE_TTL);

                    return $this->languageRepo->getEnabledCodes();
                }
            );
        } catch (InvalidArgumentException) {
            return $this->languageRepo->getEnabledCodes();
        }
    }

    public function isValidCode(string $code): bool
    {
        return in_array($code, $this->getEnabledCodes(), true);
    }

    public function invalidateCache(): void
    {
        try {
            $this->appCache->delete(self::CACHE_KEY_ENABLED_CODES);
            $this->appCache->delete(self::CACHE_KEY_ALL_LANGUAGES);
        } catch (InvalidArgumentException) {
            // Ignore cache invalidation errors
        }
    }

    public function getLocaleRegexPattern(): string
    {
        $codes = $this->getEnabledCodes();

        return $codes === [] ? 'en' : implode('|', $codes);
    }

    /**
     * @return Language[]
     */
    public function getAllLanguages(): array
    {
        try {
            return $this->appCache->get(
                self::CACHE_KEY_ALL_LANGUAGES,
                function (ItemInterface $item): array {
                    $item->expiresAfter(self::CACHE_TTL);

                    return $this->languageRepo->findAllOrdered();
                }
            );
        } catch (InvalidArgumentException) {
            return $this->languageRepo->findAllOrdered();
        }
    }

    public function findByCode(string $code): ?Language
    {
        return $this->languageRepo->findByCode($code);
    }
}
