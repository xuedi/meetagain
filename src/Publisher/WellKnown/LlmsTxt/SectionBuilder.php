<?php declare(strict_types=1);

namespace App\Publisher\WellKnown\LlmsTxt;

use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SectionBuilder
{
    public function __construct(
        private CmsRepository $cmsRepository,
        private CmsFilterService $cmsFilterService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return list<Section>
     */
    public function build(Request $request, string $locale): array
    {
        return [
            new Section('Events', [
                new Link('Upcoming events', $this->generate('app_event', $locale)),
                new Link('Featured events', $this->generate('app_event_featured', $locale)),
            ]),
            new Section('Members', [
                new Link('Member directory', $this->generate('app_member', $locale)),
            ]),
            new Section('Pages', $this->collectCmsLinks($locale)),
            new Section('Optional', [
                new Link('Sitemap', $request->getSchemeAndHttpHost() . '/sitemap.xml'),
                new Link('Contact', $this->generate('app_contact', $locale)),
            ]),
        ];
    }

    /**
     * @return list<Link>
     */
    private function collectCmsLinks(string $locale): array
    {
        $pages = $this->cmsRepository->findPublished();

        $filterResult = $this->cmsFilterService->getCmsIdFilter();
        if ($filterResult->hasActiveFilter()) {
            $allowedIds = array_flip($filterResult->getCmsIds() ?? []);
            $pages = array_filter($pages, static fn($page): bool => isset($allowedIds[$page->getId()]));
        }

        $links = [];
        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === null || !in_array($locale, $page->getLanguages(), true)) {
                continue;
            }

            $title = $page->getPageTitle($locale) ?? $page->getLinkName($locale) ?? $slug;

            $links[] = new Link($title, $this->generate('app_catch_all', $locale, ['page' => $slug]));
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generate(string $route, string $locale, array $parameters = []): string
    {
        return $this->urlGenerator->generate(
            $route,
            ['_locale' => $locale, ...$parameters],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
