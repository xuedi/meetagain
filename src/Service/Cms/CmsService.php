<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\BlockType\BlockType;
use App\Entity\Cms;
use App\Enum\CmsBlock\CmsBlockType;
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
    private const string EVENT_TEASER_TAG = 'cms_event_teaser';

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

    public function findPage(int $id): ?Cms
    {
        return $this->repo->find($id);
    }

    /**
     * @param array<int>|null $ids
     * @return array<Cms>
     */
    public function findPagesByIds(?array $ids): array
    {
        return $this->repo->findByIds($ids);
    }

    public function handle(string $locale, string $slug, Response $response): Response
    {
        $cmsFilterResult = $this->cmsFilterService->getCmsIdFilter();

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
            $blocks = $cms->getLanguageFilteredBlockJsonList($locale);
            if ($blocks->count() === 0) {
                return new Response($this->twig->render('cms/204.html.twig', [
                    'message' => 'cms.error_204_default_message',
                ]), Response::HTTP_NO_CONTENT);
            }

            $body = $this->twig->render('cms/_blocks.html.twig', ['blocks' => $blocks]);
            $hasEventTeaser = $blocks->exists(static fn(int|string $key, BlockType $block): bool => $block::getType() === CmsBlockType::EventTeaser);
            $this->storeCachedBody($cacheKey, $pageId, $body, $hasEventTeaser);
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

    public function invalidateEventTeasers(): void
    {
        $this->cache->invalidateTags([self::EVENT_TEASER_TAG]);
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
        $body = $this->cache->get($cacheKey, static function (ItemInterface $item) use (&$miss): string {
            $miss = true;
            $item->expiresAfter(1);

            return '';
        });

        return $miss ? null : $body;
    }

    private function storeCachedBody(string $cacheKey, int $pageId, string $body, bool $hasEventTeaser): void
    {
        $tags = ['cms_page_' . $pageId, 'cms_page_all'];
        if ($hasEventTeaser) {
            $tags[] = self::EVENT_TEASER_TAG;
        }

        $this->cache->get(
            $cacheKey,
            static function (ItemInterface $item) use ($body, $tags): string {
                $item->tag($tags);

                return $body;
            },
            \INF,
        );
    }
}
