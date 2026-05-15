<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterService;
use App\Repository\CmsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

readonly class CmsService
{
    public function __construct(
        private Environment $twig,
        private CmsRepository $repo,
        private EventFilterService $eventFilterService,
        private CmsFilterService $cmsFilterService,
        #[Autowire(service: 'cache.cms_page_cache')]
        private TagAwareCacheInterface $cache,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
    ) {}

    public function getSites(): array
    {
        return $this->repo->findAll();
    }

    public function handle(string $locale, string $slug, Response $response): Response
    {
        // Apply CMS filtering based on current context (determines access, not content)
        $cmsFilterResult = $this->cmsFilterService->getCmsIdFilter();

        // check if we have this page
        $cms = $this->repo->findPublishedBySlug($slug, $cmsFilterResult->getCmsIds());
        if ($cms === null) {
            throw new NotFoundHttpException();
        }

        $pageId = (int) $cms->getId();
        $host = $this->requestStack->getCurrentRequest()?->getHost() ?? '';
        $eventIds = $this->eventFilterService->getEventIdFilter()->getEventIds();
        $cacheKey = $this->getCacheKey($pageId, $locale, $slug, $host, $eventIds);

        $body = $this->getCachedBody($cacheKey);
        if ($body === null) {
            // collect the blocks in order
            $blocks = $cms->getLanguageFilteredBlockJsonList($locale);
            if ($blocks->count() === 0) {
                return new Response($this->twig->render('cms/204.html.twig', [
                    'message' => 'cms.error_204_default_message',
                ]), Response::HTTP_NO_CONTENT);
            }

            $body = $this->twig->render('cms/_blocks.html.twig', ['blocks' => $blocks]);
            $this->storeCachedBody($cacheKey, $pageId, $body);
        }

        $content = $this->twig->render('cms/index.html.twig', [
            'title' => $cms->getPageTitle($locale) ?? $this->translator->trans('cms.page_no_title_fallback'),
            'body' => $body,
        ]);

        $response->setContent($content);
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    public function invalidatePage(int $pageId): void
    {
        $this->cache->invalidateTags(['cms_page_' . $pageId]);
    }

    public function invalidateAll(): void
    {
        $this->cache->invalidateTags(['cms_page_all']);
    }

    public function invalidateMenuCaches(): void
    {
        $this->cache->invalidateTags(['cms_menu']);
    }

    /**
     * @param array<int>|null $eventIds
     */
    private function getCacheKey(int $pageId, string $locale, string $slug, string $host, ?array $eventIds): string
    {
        $keyChunks = [
            'locale' => $locale,
            'slug' => $slug,
            'host' => $host,
            'events' => $this->computeEventFilterFingerprint($eventIds),
        ];

        return 'cms_page.' . $pageId . '.' . md5(serialize($keyChunks));
    }

    /**
     * @param array<int>|null $eventIds
     */
    private function computeEventFilterFingerprint(?array $eventIds): string
    {
        if ($eventIds === null) {
            return 'global';
        }

        $sorted = $eventIds;
        sort($sorted);

        return md5(implode(',', $sorted));
    }

    private function getCachedBody(string $cacheKey): ?string
    {
        $miss = false;

        // On a cache miss the callback is invoked; on a hit it is skipped.
        // The 1s sentinel TTL keeps the miss marker from polluting the cache;
        // storeCachedBody() uses beta=INF to overwrite it immediately with the real HTML.
        $body = $this->cache->get($cacheKey, static function (ItemInterface $item) use (&$miss): string {
            $miss = true;
            $item->expiresAfter(1);

            return '';
        });

        return $miss ? null : $body;
    }

    private function storeCachedBody(string $cacheKey, int $pageId, string $body): void
    {
        $tag = 'cms_page_' . $pageId;

        $this->cache->get(
            $cacheKey,
            static function (ItemInterface $item) use ($body, $tag): string {
                $item->tag([$tag, 'cms_page_all']);

                return $body;
            },
            \INF,
        );
    }
}
