<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SitemapController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CmsRepository $cmsRepository,
        private readonly LanguageService $languageService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route('/sitemap', name: 'app_admin_system_sitemap')]
    public function sitemap(): Response
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $defaultLocale = $this->languageService->getFilteredDefaultLocale();

        $urls = [];

        $staticRoutes = [
            ['route' => 'app_default', 'params' => [], 'section' => 'static', 'label' => 'Home'],
            ['route' => 'app_event', 'params' => [], 'section' => 'static', 'label' => 'Events list'],
            ['route' => 'app_member', 'params' => ['page' => 1], 'section' => 'static', 'label' => 'Members list'],
        ];

        foreach ($staticRoutes as $route) {
            $urls[] = [
                'section' => $route['section'],
                'label' => $route['label'],
                'url' => $this->urlGenerator->generate(
                    $route['route'],
                    ['_locale' => $defaultLocale, ...$route['params']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'locales' => count($locales),
                'lastmod' => date('Y-m-d'),
                'warnings' => [],
            ];
        }

        foreach ($this->cmsRepository->findPublished() as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }

            $warnings = [];
            if ($page->getPageTitle($defaultLocale) === null || $page->getPageTitle($defaultLocale) === '') {
                $warnings[] = 'Missing title in default locale';
            }

            $urls[] = [
                'section' => 'cms',
                'label' => $page->getPageTitle($defaultLocale) ?? $slug,
                'url' => $this->urlGenerator->generate(
                    'app_catch_all',
                    ['_locale' => $defaultLocale, 'page' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'locales' => count($locales),
                'lastmod' => $page->getCreatedAt()?->format('Y-m-d') ?? '-',
                'warnings' => $warnings,
            ];
        }

        foreach ($this->eventRepository->findForSitemap() as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }

            $warnings = [];
            if ($event->getTitle($defaultLocale) === '') {
                $warnings[] = 'Missing title in default locale';
            }
            if ($event->getPreviewImage() === null) {
                $warnings[] = 'No preview image';
            }

            $urls[] = [
                'section' => 'events',
                'label' => $event->getTitle($defaultLocale) ?: "Event #{$id}",
                'url' => $this->urlGenerator->generate(
                    'app_event_details',
                    ['_locale' => $defaultLocale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'locales' => count($locales),
                'lastmod' => $event->getStart()->format('Y-m-d'),
                'warnings' => $warnings,
            ];
        }

        return $this->render('admin/system/sitemap/index.html.twig', [
            'active' => 'system',
            'urls' => $urls,
            'total' => count($urls),
        ]);
    }
}
