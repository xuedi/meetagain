<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Sitemap\Url\GoogleMultilangUrlDecorator;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CoreSitemapSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        private CmsFilterService $cmsFilterService,
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

        $staticRoutes = [
            ['route' => 'app_default', 'params' => [], 'priority' => 1.0],
            ['route' => 'app_event', 'params' => [], 'priority' => 0.9],
            ['route' => 'app_member', 'params' => ['page' => 1], 'priority' => 0.7],
        ];

        // Pre-build all locale URLs per route so each locale entry can reference them all
        foreach ($staticRoutes as $routeConfig) {
            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    $routeConfig['route'],
                    ['_locale' => $locale, ...$routeConfig['params']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            // One <url> entry per locale so each language URL appears as a <loc>
            foreach ($locales as $locale) {
                $url = new UrlConcrete($localeUrls[$locale], priority: $routeConfig['priority']);
                $decorated = new GoogleMultilangUrlDecorator($url);
                foreach ($localeUrls as $altLocale => $altUrl) {
                    $decorated->addLink($altUrl, $altLocale);
                }
                $event->getUrlContainer()->addUrl($decorated, 'static');
            }
        }
    }

    private function populateCms(SitemapPopulateEvent $event): void
    {
        $locales = $this->languageService->getFilteredEnabledCodes();

        $pages = $this->cmsRepository->findPublished();

        $filterResult = $this->cmsFilterService->getCmsIdFilter();
        if ($filterResult->hasActiveFilter()) {
            $allowedIdSet = array_flip($filterResult->getCmsIds() ?? []);
            $pages = array_filter($pages, static fn($p) => isset($allowedIdSet[$p->getId()]));
        }

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }

            $lastmod = new \DateTimeImmutable($page->getCreatedAt()?->format('Y-m-d') ?? date('Y-m-d'));

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_catch_all',
                    ['_locale' => $locale, 'page' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $url = new UrlConcrete($localeUrls[$locale], lastmod: $lastmod, priority: 0.7);
                $decorated = new GoogleMultilangUrlDecorator($url);
                foreach ($localeUrls as $altLocale => $altUrl) {
                    $decorated->addLink($altUrl, $altLocale);
                }
                $event->getUrlContainer()->addUrl($decorated, 'cms');
            }
        }
    }

    private function populateEvents(SitemapPopulateEvent $event): void
    {
        $locales = $this->languageService->getFilteredEnabledCodes();

        foreach ($this->eventRepository->findForSitemap() as $eventEntity) {
            $id = $eventEntity->getId();
            if ($id === null) {
                continue;
            }

            $lastmod = new \DateTimeImmutable($eventEntity->getStart()->format('Y-m-d'));

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_event_details',
                    ['_locale' => $locale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $url = new UrlConcrete($localeUrls[$locale], lastmod: $lastmod, priority: 0.6);
                $decorated = new GoogleMultilangUrlDecorator($url);
                foreach ($localeUrls as $altLocale => $altUrl) {
                    $decorated->addLink($altUrl, $altLocale);
                }
                $event->getUrlContainer()->addUrl($decorated, 'events');
            }
        }
    }
}
