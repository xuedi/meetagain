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
}
