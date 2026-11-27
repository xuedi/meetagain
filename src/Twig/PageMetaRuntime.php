<?php declare(strict_types=1);

namespace App\Twig;

use App\Publisher\MetaDescription\MetaDescriptionProviderInterface;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\NoindexService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Markup;

final readonly class PageMetaRuntime implements RuntimeExtensionInterface
{
    private const int LD_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    public const int TITLE_MAX_LENGTH = 65;

    /**
     * @param iterable<MetaDescriptionProviderInterface>   $metaDescriptionProviders
     * @param iterable<OrganizationSchemaProviderInterface> $organizationProviders
     */
    public function __construct(
        private RequestStack $requestStack,
        private ConfigService $configService,
        private CanonicalUrlService $canonicalUrlService,
        private SiteNameResolver $siteNameResolver,
        private NoindexService $noindexService,
        private TranslatorInterface $translator,
        #[AutowireIterator(MetaDescriptionProviderInterface::class)]
        private iterable $metaDescriptionProviders = [],
        #[AutowireIterator(OrganizationSchemaProviderInterface::class)]
        private iterable $organizationProviders = [],
    ) {}

    public function isNoindex(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request instanceof Request && $this->noindexService->shouldNoindex($request);
    }

    public function getCanonicalUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return rtrim($this->configService->getHost(), '/') . '/';
        }

        return $this->canonicalUrlService->getCanonicalUrl($request);
    }

    public function getSiteName(): string
    {
        return $this->siteNameResolver->resolve();
    }

    public function getPageTitle(string $title, string $place = ''): string
    {
        $title = $this->plainText($title);
        $siteName = $this->siteNameResolver->resolve();
        if ($title !== '' && $this->mentions($title, $siteName)) {
            return $title;
        }

        $place = $this->plainText($place);
        if ($place !== '' && $this->mentions($title, $place)) {
            $place = '';
        }

        $parameters = ['%title%' => $title, '%site%' => $siteName, '%place%' => $place];
        if ($title === '') {
            return $place === '' ? $siteName : $this->translator->trans('chrome.title_site_place', $parameters);
        }

        $keys = $place === '' ? ['chrome.title_page_site'] : ['chrome.title_page_site_place', 'chrome.title_page_site'];
        foreach ($keys as $key) {
            $composed = $this->translator->trans($key, $parameters);
            if (mb_strlen($composed) <= self::TITLE_MAX_LENGTH) {
                return $composed;
            }
        }

        return $title;
    }

    public function getMetaDescription(string $context = 'default', ?string $pageFallback = null): string
    {
        foreach ($this->metaDescriptionProviders as $provider) {
            $value = $provider->getMetaDescription($context);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        if ($pageFallback !== null && $pageFallback !== '') {
            return $pageFallback;
        }

        $systemValue = $this->configService->getSeoDescription($context);
        if ($systemValue !== '') {
            return $systemValue;
        }

        return $this->translator->trans(match ($context) {
            'events' => 'chrome.meta_description_events',
            'members' => 'chrome.meta_description_members',
            default => 'chrome.meta_description_default',
        });
    }

    public function getOrganizationSchema(): Markup
    {
        foreach ($this->organizationProviders as $provider) {
            $schema = $provider->getOrganizationSchema();
            if ($schema !== null) {
                return $this->ldJson(array_merge(['@context' => 'https://schema.org'], $schema));
            }
        }

        $host = rtrim($this->configService->getHost(), '/');

        return $this->ldJson([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $host . '/#organization',
            'name' => 'MeetAgain',
            'url' => $host,
        ]);
    }

    /** @param array<array-key, mixed> $data */
    public function ldJson(array $data): Markup
    {
        return new Markup(json_encode($data, self::LD_JSON_FLAGS) ?: '{}', 'UTF-8');
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function mentions(string $title, string $name): bool
    {
        $name = $this->lettersAndDigits($name);

        return $name === '' || str_contains($this->lettersAndDigits($title), $name);
    }

    private function lettersAndDigits(string $text): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text));
    }
}
