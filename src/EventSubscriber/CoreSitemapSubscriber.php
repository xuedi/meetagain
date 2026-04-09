<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Filter\Sitemap\CmsSitemapFilterInterface;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Sitemap\Url\GoogleMultilangUrlDecorator;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CoreSitemapSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<CmsSitemapFilterInterface> $cmsSitemapFilters
     */
    public function __construct(
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        #[AutowireIterator(CmsSitemapFilterInterface::class)]
        private iterable $cmsSitemapFilters,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SitemapPopulateEvent::class => 'populate',
        ];
    }

    public function populate(SitemapPopulateEvent $event): void
    {
        $section = $event->getSection();

        if ($section === null || $section === 'static') {
            $this->populateStatic($event);
        }

        if ($section === null || $section === 'cms') {
            $this->populateCms($event);
        }

        if ($section === null || $section === 'events') {
            $this->populateEvents($event);
        }
    }

    private function populateStatic(SitemapPopulateEvent $event): void
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $defaultLocale = $this->languageService->getFilteredDefaultLocale();

        $staticRoutes = [
            ['route' => 'app_default', 'params' => [], 'priority' => 1.0],
            ['route' => 'app_event', 'params' => [], 'priority' => 0.9],
            ['route' => 'app_member', 'params' => ['page' => 1], 'priority' => 0.7],
        ];

        foreach ($staticRoutes as $routeConfig) {
            $defaultUrl = $this->urlGenerator->generate(
                $routeConfig['route'],
                ['_locale' => $defaultLocale, ...$routeConfig['params']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $url = new UrlConcrete($defaultUrl, priority: $routeConfig['priority']);
            $decorated = new GoogleMultilangUrlDecorator($url);

            foreach ($locales as $locale) {
                $localeUrl = $this->urlGenerator->generate(
                    $routeConfig['route'],
                    ['_locale' => $locale, ...$routeConfig['params']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $decorated->addLink($localeUrl, $this->languageService->toHreflangCode($locale));
            }

            $event->getUrlContainer()->addUrl($decorated, 'static');
        }
    }

    private function populateCms(SitemapPopulateEvent $event): void
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $defaultLocale = $this->languageService->getFilteredDefaultLocale();

        $pages = $this->cmsRepository->findPublished();

        // Apply sitemap CMS filters (e.g. group-scoping in multisite)
        $allowedIds = array_values(array_filter(array_map(static fn($p) => $p->getId(), $pages)));
        foreach ($this->cmsSitemapFilters as $filter) {
            $allowedIds = $filter->filterCmsIds($allowedIds);
        }
        $allowedIdSet = array_flip($allowedIds);
        $pages = array_filter($pages, static fn($p) => isset($allowedIdSet[$p->getId()]));

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }

            $defaultUrl = $this->urlGenerator->generate(
                'app_catch_all',
                ['_locale' => $defaultLocale, 'page' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $lastmod = $page->getCreatedAt()?->format('Y-m-d') ?? date('Y-m-d');
            $url = new UrlConcrete($defaultUrl, lastmod: new \DateTimeImmutable($lastmod), priority: 0.7);
            $decorated = new GoogleMultilangUrlDecorator($url);

            foreach ($locales as $locale) {
                $localeUrl = $this->urlGenerator->generate(
                    'app_catch_all',
                    ['_locale' => $locale, 'page' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $decorated->addLink($localeUrl, $this->languageService->toHreflangCode($locale));
            }

            $event->getUrlContainer()->addUrl($decorated, 'cms');
        }
    }

    private function populateEvents(SitemapPopulateEvent $event): void
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $defaultLocale = $this->languageService->getFilteredDefaultLocale();

        foreach ($this->eventRepository->findForSitemap() as $eventEntity) {
            $id = $eventEntity->getId();
            if ($id === null) {
                continue;
            }

            $defaultUrl = $this->urlGenerator->generate(
                'app_event_details',
                ['_locale' => $defaultLocale, 'id' => $id],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $lastmod = $eventEntity->getStart()->format('Y-m-d');
            $url = new UrlConcrete($defaultUrl, lastmod: new \DateTimeImmutable($lastmod), priority: 0.6);
            $decorated = new GoogleMultilangUrlDecorator($url);

            foreach ($locales as $locale) {
                $localeUrl = $this->urlGenerator->generate(
                    'app_event_details',
                    ['_locale' => $locale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $decorated->addLink($localeUrl, $this->languageService->toHreflangCode($locale));
            }

            $event->getUrlContainer()->addUrl($decorated, 'events');
        }
    }
}
