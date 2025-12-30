<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\EventRepository;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class SitemapService
{
    public function __construct(
        private Environment $twig,
        private CmsService $cms,
        private EventRepository $events,
        private LanguageService $languageService,
    ) {
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function getContent(string $host): Response
    {
        $sites = [];

        $locales = $this->languageService->getEnabledCodes();
        foreach ($locales as $locale) {
            $sites = [
                ...$sites,
                ...$this->getAllSitesForLocale($host, $locale),
            ];
        }

        return new Response(
            $this->twig->render('sitemap/index.xml.twig', [
                'sites' => $sites,
            ]),
            Response::HTTP_OK,
            ['Content-Type' => 'text/xml'],
        );
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, prio: float}>
     */
    private function getAllSitesForLocale(string $host, string $locale): array
    {
        return [
            ...$this->getCmsPages($host, $locale),
            ...$this->getStaticPages($host, $locale),
            ...$this->getEventPages($host, $locale),
        ];
    }

    private function getCmsPages(string $host, string $locale): array
    {
        $sites = [];
        foreach ($this->cms->getSites() as $site) {
            $sites[] = [
                'loc' => sprintf('https://%s/%s/%s', $host, $locale, $site->getSlug()),
                'lastmod' => $site->getCreatedAt()->format('Y-m-d'),
                'prio' => 0.7,
            ];
        }
        return $sites;
    }

    private function getStaticPages(string $host, string $locale): array
    {
        $sites = [];
        $now = new DateTime();
        $date = $now->format('Y-m-d');
        foreach (['', 'events', 'members'] as $site) {
            $sites[] = [
                'loc' => sprintf('https://%s/%s/%s', $host, $locale, $site),
                'lastmod' => $date,
                'prio' => 0.9,
            ];
        }
        return $sites;
    }

    private function getEventPages(string $host, mixed $locale): array
    {
        $sites = [];
        foreach ($this->events->findAll() as $event) {
            $sites[] = [
                'loc' => sprintf('https://%s/%s/event/%d', $host, $locale, $event->getId()),
                'lastmod' => $event->getStart()->format('Y-m-d'),
                'prio' => 0.6,
            ];
        }
        return $sites;
    }
}
