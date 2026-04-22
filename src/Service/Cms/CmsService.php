<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterService;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

readonly class CmsService
{
    public function __construct(
        private Environment $twig,
        private CmsRepository $repo,
        private EventRepository $eventRepo,
        private EventFilterService $eventFilterService,
        private CmsFilterService $cmsFilterService,
        private CmsPageCacheService $cmsPageCacheService,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getSites(): array
    {
        return $this->repo->findAll();
    }

    public function handle(string $locale, string $slug, Response $response): Response
    {
        // Apply CMS filtering based on current context (determines access, not content)
        $cmsFilterResult = $this->cmsFilterService->getCmsIdFilter();

        $cms = $this->repo->findPublishedBySlug($slug, $cmsFilterResult->getCmsIds());

        if ($cms === null) {
            return $this->createNotFoundPage();
        }

        $blocks = $cms->getLanguageFilteredBlockJsonList($locale);
        if ($blocks->count() === 0) {
            return new Response($this->twig->render('cms/204.html.twig', [
                'message' => 'cms.error_204_default_message',
            ]), Response::HTTP_NO_CONTENT);
        }

        // Apply event filter — affects which events are embedded in the page content
        $filterResult = $this->eventFilterService->getEventIdFilter();
        $eventIds = $filterResult->getEventIds();

        // Only cache for anonymous visitors. Authenticated users get a fresh render
        // because the full HTML includes user-specific content (navigation, user menu).
        $anonymous = !$this->security->isGranted('IS_AUTHENTICATED');

        $pageId = (int) $cms->getId();

        if ($anonymous) {
            $cached = $this->cmsPageCacheService->get($pageId, $locale, $eventIds);
            if ($cached !== null) {
                $response->setContent($cached);
                $response->setStatusCode(Response::HTTP_OK);

                return $response;
            }
        }

        $content = $this->twig->render('cms/index.html.twig', [
            'title' => $cms->getPageTitle($locale) ?? $this->translator->trans('cms.page_no_title_fallback'),
            'blocks' => $blocks,
            'events' => $this->eventRepo->getUpcomingEvents(3, $eventIds),
        ]);

        if ($anonymous) {
            $this->cmsPageCacheService->store($pageId, $locale, $eventIds, $content);
        }

        $response->setContent($content);
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    public function createNotFoundPage(): Response
    {
        return new Response($this->twig->render('cms/404.html.twig', [
            'message' => 'cms.error_404_default_message',
        ]), Response::HTTP_NOT_FOUND);
    }
}
