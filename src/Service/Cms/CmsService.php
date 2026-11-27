<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\BlockType\BlockType;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Gallery;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\TrioCards;
use App\Entity\Cms;
use App\Enum\CmsBlock\CmsBlockType;
use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterService;
use App\Repository\CmsRepository;
use App\Repository\ImageRepository;
use App\Service\Config\SiteNameResolver;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\TruncateMode;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;

use function Symfony\Component\String\u;

readonly class CmsService
{
    public const int DESCRIPTION_LENGTH = 155;
    private const string HOME_SLUG = 'index';

    private const string EVENT_TEASER_TAG = 'cms_event_teaser';
    private const string IMAGE_TAG_PREFIX = 'cms_image_';

    public function __construct(
        private Environment $twig,
        private CmsRepository $repo,
        private EventFilterService $eventFilterService,
        private CmsFilterService $cmsFilterService,
        #[Autowire(service: 'cache.cms_page_cache')]
        private TagAwareCacheInterface $cache,
        private RequestStack $requestStack,
        private ImageRepository $imageRepo,
        private SiteNameResolver $siteNameResolver,
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

        $body = $this->cache->get($cacheKey, fn(ItemInterface $item): ?string => $this->renderBody($item, $cms, $pageId, $locale));
        if ($body === null) {
            return new Response($this->twig->render('cms/204.html.twig', [
                'message' => 'cms.error_204_default_message',
            ]), Response::HTTP_NO_CONTENT);
        }

        $content = $this->twig->render('cms/index.html.twig', [
            'title' => trim((string) $cms->getPageTitle($locale)),
            'titlePlace' => (string) $this->siteNameResolver->resolvePlace($locale),
            'description' => $this->excerpt($cms->getPageContent($locale)),
            'home' => $slug === self::HOME_SLUG,
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

    public function invalidateImage(int $imageId): void
    {
        $this->cache->invalidateTags([self::IMAGE_TAG_PREFIX . $imageId]);
    }

    public function invalidateAll(): void
    {
        $this->cache->invalidateTags(['cms_page_all']);
    }

    public function invalidateMenuCaches(): void
    {
        $this->cache->invalidateTags(['cms_menu']);
    }

    private function excerpt(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = u(html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5))->collapseWhitespace();
        if ($text->isEmpty()) {
            return null;
        }

        return $text->truncate(self::DESCRIPTION_LENGTH, '…', TruncateMode::WordBefore)->toString();
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

    private function renderBody(ItemInterface $item, Cms $cms, int $pageId, string $locale): ?string
    {
        $tags = ['cms_page_' . $pageId, 'cms_page_all'];
        $blocks = $cms->getLanguageFilteredBlockJsonList($locale);
        if ($blocks->exists(static fn(int|string $key, BlockType $block): bool => $block::getType() === CmsBlockType::EventTeaser)) {
            $tags[] = self::EVENT_TEASER_TAG;
        }
        $listedImageIds = $this->collectListedImageIds($blocks);
        foreach (array_unique([...$listedImageIds, ...$this->collectBlockImageIds($blocks)]) as $imageId) {
            $tags[] = self::IMAGE_TAG_PREFIX . $imageId;
        }
        $item->tag($tags);

        if ($blocks->count() === 0) {
            return null;
        }

        return $this->twig->render('cms/_blocks.html.twig', [
            'blocks' => $blocks,
            'images' => $this->imageRepo->findByIdsIndexed($listedImageIds),
        ]);
    }

    /**
     * @param Collection<int|string, BlockType> $blocks
     * @return list<int>
     */
    private function collectBlockImageIds(Collection $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $image = match (true) {
                $block instanceof Text, $block instanceof Hero, $block instanceof EventTeaser => $block->image,
                default => null,
            };
            if ($image?->getId() !== null) {
                $ids[] = (int) $image->getId();
            }
        }

        return $ids;
    }

    /**
     * @param Collection<int|string, BlockType> $blocks
     * @return list<int>
     */
    private function collectListedImageIds(Collection $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $refs = match (true) {
                $block instanceof Gallery => $block->images,
                $block instanceof TrioCards => array_column($block->cards, 'image'),
                default => [],
            };
            foreach ($refs as $ref) {
                if (isset($ref['id'])) {
                    $ids[] = (int) $ref['id'];
                }
            }
        }

        return $ids;
    }
}
