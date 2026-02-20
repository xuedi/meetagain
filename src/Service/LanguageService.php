<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Language;
use App\Filter\Admin\Language\AdminLanguageFilterService;
use App\Filter\Language\LanguageFilterService;
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
        private LanguageFilterService $languageFilterService,
        private AdminLanguageFilterService $adminLanguageFilterService,
    ) {}

    /**
     * @return string[] Array of enabled language codes ordered by sortOrder
     */
    public function getEnabledCodes(): array
    {
        try {
            return $this->appCache->get(self::CACHE_KEY_ENABLED_CODES, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->languageRepo->getEnabledCodes();
            });
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
            // Cache invalidation failures are non-critical - cache will be refreshed on next request
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
            return $this->appCache->get(self::CACHE_KEY_ALL_LANGUAGES, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->languageRepo->findAllOrdered();
            });
        } catch (InvalidArgumentException) {
            return $this->languageRepo->findAllOrdered();
        }
    }

    public function findByCode(string $code): ?Language
    {
        return $this->languageRepo->findByCode($code);
    }

    /**
     * Get enabled language codes filtered by current context.
     * This applies any registered language filters (e.g., from plugins).
     *
     * @return string[] Array of filtered language codes
     */
    public function getFilteredEnabledCodes(): array
    {
        $enabledCodes = $this->getEnabledCodes();
        $filterResult = $this->languageFilterService->getLanguageCodeFilter();

        if (!$filterResult->hasActiveFilter()) {
            return $enabledCodes;
        }

        $filteredCodes = $filterResult->getLanguageCodes();
        if ($filteredCodes === null || $filteredCodes === []) {
            return $enabledCodes; // Fallback: if filter produces empty, show all rather than nothing
        }

        $result = array_values(array_intersect($enabledCodes, $filteredCodes));

        return $result === [] ? $enabledCodes : $result; // Safety: never return empty
    }

    /**
     * Check if a language code is valid in the filtered context.
     */
    public function isFilteredValidCode(string $code): bool
    {
        return in_array($code, $this->getFilteredEnabledCodes(), true);
    }

    /**
     * Get enabled language codes filtered for admin context.
     * This applies admin-specific language filters (e.g., group language restrictions in admin forms).
     *
     * @return string[] Array of filtered language codes
     */
    public function getAdminFilteredEnabledCodes(): array
    {
        $enabledCodes = $this->getEnabledCodes();
        $filterResult = $this->adminLanguageFilterService->getLanguageCodeFilter();

        if (!$filterResult->hasActiveFilter()) {
            return $enabledCodes;
        }

        $filteredCodes = $filterResult->getLanguageCodes();
        if ($filteredCodes === null || $filteredCodes === []) {
            return $enabledCodes; // Fallback: if filter produces empty, show all rather than nothing
        }

        $result = array_values(array_intersect($enabledCodes, $filteredCodes));

        return $result === [] ? $enabledCodes : $result; // Safety: never return empty
    }

    /**
     * Check if a language code is valid in the admin filtered context.
     */
    public function isAdminFilteredValidCode(string $code): bool
    {
        return in_array($code, $this->getAdminFilteredEnabledCodes(), true);
    }

    /**
     * Get the default locale for the current filtered context.
     * Prefers 'en' if available, otherwise returns the first filtered code.
     */
    public function getFilteredDefaultLocale(): string
    {
        $filteredCodes = $this->getFilteredEnabledCodes();
        if (in_array('en', $filteredCodes, true)) {
            return 'en';
        }

        return $filteredCodes[0] ?? 'en'; // First by sort order, or 'en' as absolute fallback
    }
}
