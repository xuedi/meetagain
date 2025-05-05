<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\EventRepository;
use DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

readonly class SitemapService
{
    public function __construct(
        private Environment $twig,
        private CmsService $cms,
        private EventRepository $events,
        private ParameterBagInterface $appParams,
    )
    {
    }

    public function getContent(string $host): Response
    {
        $sites = [];

        $locales = $this->appParams->get('kernel.enabled_locales');
        foreach ($locales as $locale) {
            $sites = array_merge($sites, $this->getCmsPages($host, $locale));
            $sites = array_merge($sites, $this->getStaticPages($host, $locale));
            $sites = array_merge($sites, $this->getEventPages($host, $locale));
        }

        //application/xml

        return new Response($this->twig->render('sitemap/index.xml.twig', [
            'sites' => $sites,
        ]), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
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
        foreach (['', 'events', 'members'] as $site) {
            $sites[] = [
                'loc' => sprintf('https://%s/%s/%s', $host, $locale, $site),
                'lastmod' => (new DateTime())->format('Y-m-d'),
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
