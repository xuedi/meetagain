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
    public const int TITLE_MAX_LENGTH = 55;
    public const int DESCRIPTION_MAX_LENGTH = 140;
    private const string ELLIPSIS = '…';

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

    public function getPageTitle(string $title, string $place = '', string $detail = ''): string
    {
        $title = $this->plainText($title);
        $siteName = $this->siteNameResolver->resolve();
        $namesSite = $title !== '' && $this->mentions($title, $siteName);

        $place = $this->plainText($place);
        if ($place !== '' && $this->mentions($title, $place)) {
            $place = '';
        }

        if ($title === '') {
            $parameters = ['%site%' => $siteName, '%place%' => $place];

            return $place === '' ? $siteName : $this->translator->trans('chrome.title_site_place', $parameters);
        }

        $keys = match (true) {
            $namesSite => [],
            $place === '' => ['chrome.title_page_site'],
            default => ['chrome.title_page_site_place', 'chrome.title_page_site'],
        };

        $detail = $this->plainText($detail);
        $candidates = $detail === '' ? [$title] : [$this->translator->trans('chrome.title_page_detail', ['%title%' => $title, '%detail%' => $detail]), $title];

        foreach ($candidates as $candidate) {
            $parameters = ['%title%' => $candidate, '%site%' => $siteName, '%place%' => $place];
            $composed = array_map(fn(string $key): string => $this->translator->trans($key, $parameters), $keys);
            foreach ([...$composed, $candidate] as $option) {
                if (mb_strwidth($option) <= self::TITLE_MAX_LENGTH) {
                    return $option;
                }
            }
        }

        return $title;
    }

    public function getPageDescription(string $text, string $suffix = ''): string
    {
        $text = $this->plainText($text);
        $suffix = $this->plainText($suffix);
        if ($text === '') {
            return $suffix;
        }

        $tail = $suffix === '' ? '' : ' - ' . $suffix;
        $room = self::DESCRIPTION_MAX_LENGTH - mb_strwidth($tail);
        if (mb_strwidth($text) <= $room) {
            return $text . $tail;
        }

        return $this->cutBeforeAWord($text, max(1, $room - mb_strwidth(self::ELLIPSIS))) . self::ELLIPSIS . $tail;
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

    private function cutBeforeAWord(string $text, int $width): string
    {
        $head = mb_strimwidth($text, 0, $width);
        $space = mb_strrpos($head, ' ');
        if ($space !== false && mb_substr($text, mb_strlen($head), 1) !== ' ') {
            $head = mb_substr($head, 0, $space);
        }

        return (string) preg_replace('/[\s,;:.\-–]+$/u', '', $head);
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
