<?php declare(strict_types=1);

namespace App\Controller\NonLocale\Api;

use App\Controller\AbstractController;
use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Enum\MenuLocation;
use App\Repository\CmsBlockRepository;
use App\Repository\CmsRepository;
use App\Service\Cms\CmsPageCacheService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cms'), IsGranted('ROLE_ADMIN')]
final class CmsCrudApiController extends AbstractController
{
    public function __construct(
        private readonly CmsRepository $cmsRepository,
        private readonly CmsBlockRepository $blockRepository,
        private readonly EntityManagerInterface $em,
        private readonly CmsPageCacheService $cmsPageCacheService,
    ) {}

    #[Route('/', name: 'app_api_cms_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $pages = $this->cmsRepository->findAll();

        $result = array_map(static function (Cms $cms): array {
            $titles = [];
            foreach ($cms->getTitles() as $title) {
                $titles[$title->getLanguage()] = $title->getTitle();
            }

            return [
                'id' => $cms->getId(),
                'slug' => $cms->getSlug(),
                'published' => $cms->isPublished(),
                'locked' => $cms->isLocked(),
                'titles' => $titles,
            ];
        }, $pages);

        return new JsonResponse($result);
    }

    #[Route('/{id}', name: 'app_api_cms_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializePage($cms));
    }

    #[Route('/', name: 'app_api_cms_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $slug = (string) ($data['slug'] ?? '');

        if ($slug === '') {
            return new JsonResponse(['error' => 'slug is required'], Response::HTTP_BAD_REQUEST);
        }

        $cms = new Cms();
        $cms->setSlug($slug);
        $cms->setPublished(false);
        $cms->setLocked(false);
        $cms->setCreatedAt(new DateTimeImmutable());
        $cms->setCreatedBy($this->getAuthedUser());

        foreach ((array) ($data['titles'] ?? []) as $lang => $titleText) {
            $title = new CmsTitle();
            $title->setLanguage((string) $lang);
            $title->setTitle((string) $titleText);
            $cms->addTitle($title);
            $this->em->persist($title);
        }

        foreach ((array) ($data['linkNames'] ?? []) as $lang => $nameText) {
            $linkName = new CmsLinkName();
            $linkName->setLanguage((string) $lang);
            $linkName->setName((string) $nameText);
            $cms->addLinkName($linkName);
            $this->em->persist($linkName);
        }

        foreach ((array) ($data['menuLocations'] ?? []) as $locationValue) {
            $location = MenuLocation::tryFrom((int) $locationValue);
            if ($location !== null) {
                $menuLocation = new CmsMenuLocation();
                $menuLocation->setLocation($location);
                $cms->addMenuLocation($menuLocation);
                $this->em->persist($menuLocation);
            }
        }

        $this->em->persist($cms);
        $this->em->flush();

        if (isset($data['menuLocations'])) {
            $this->cmsPageCacheService->invalidateMenuCaches();
        }

        return new JsonResponse($this->serializePage($cms), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_api_cms_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['slug'])) {
            $cms->setSlug((string) $data['slug']);
        }

        if (isset($data['published'])) {
            $cms->setPublished((bool) $data['published']);
        }

        if (isset($data['titles']) && is_array($data['titles'])) {
            $this->updateTitles($cms, $data['titles']);
        }

        if (isset($data['linkNames']) && is_array($data['linkNames'])) {
            $this->updateLinkNames($cms, $data['linkNames']);
        }

        $menuLocationsChanged = isset($data['menuLocations']) && is_array($data['menuLocations']);
        if ($menuLocationsChanged) {
            $this->updateMenuLocations($cms, $data['menuLocations']);
        }

        $this->em->flush();

        if ($cms->getId() !== null) {
            $this->cmsPageCacheService->invalidatePage($cms->getId());
        }

        if ($menuLocationsChanged) {
            $this->cmsPageCacheService->invalidateMenuCaches();
        }

        return new JsonResponse($this->serializePage($cms));
    }

    #[Route('/{id}', name: 'app_api_cms_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        if ($cms->isLocked()) {
            return new JsonResponse(['error' => 'Page is locked and cannot be deleted'], Response::HTTP_FORBIDDEN);
        }

        $pageId = $cms->getId();
        $this->em->remove($cms);
        $this->em->flush();

        if ($pageId !== null) {
            $this->cmsPageCacheService->invalidatePage($pageId);
        }

        $this->cmsPageCacheService->invalidateMenuCaches();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/blocks', name: 'app_api_cms_block_add', methods: ['POST'])]
    public function addBlock(int $id, Request $request): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $language = (string) ($data['language'] ?? '');
        $typeValue = (int) ($data['type'] ?? 0);
        $priority = (float) ($data['priority'] ?? ($this->blockRepository->getMaxPriority() + 1));
        $json = (array) ($data['json'] ?? []);

        $type = CmsBlockType::tryFrom($typeValue);
        if ($type === null) {
            return new JsonResponse(['error' => 'Invalid block type'], Response::HTTP_BAD_REQUEST);
        }

        if ($language === '') {
            return new JsonResponse(['error' => 'language is required'], Response::HTTP_BAD_REQUEST);
        }

        $block = new CmsBlock();
        $block->setLanguage($language);
        $block->setPriority($priority);
        $block->setType($type);
        $block->setJson($json);

        $cms->addBlock($block);
        $this->em->persist($block);
        $this->em->flush();

        if ($cms->getId() !== null) {
            $this->cmsPageCacheService->invalidatePage($cms->getId());
        }

        return new JsonResponse($this->serializeBlock($block), Response::HTTP_CREATED);
    }

    #[Route('/{id}/blocks/{blockId}', name: 'app_api_cms_block_update', methods: ['PUT'])]
    public function updateBlock(int $id, int $blockId, Request $request): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        $block = $this->blockRepository->find($blockId);
        if ($block === null || $block->getPage()?->getId() !== $id) {
            return new JsonResponse(['error' => 'Block not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['json'])) {
            $block->setJson((array) $data['json']);
        }

        if (isset($data['priority'])) {
            $block->setPriority((float) $data['priority']);
        }

        $this->em->flush();

        if ($cms->getId() !== null) {
            $this->cmsPageCacheService->invalidatePage($cms->getId());
        }

        return new JsonResponse($this->serializeBlock($block));
    }

    #[Route('/{id}/blocks/{blockId}', name: 'app_api_cms_block_delete', methods: ['DELETE'])]
    public function deleteBlock(int $id, int $blockId): JsonResponse
    {
        $cms = $this->cmsRepository->find($id);
        if ($cms === null) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        $block = $this->blockRepository->find($blockId);
        if ($block === null || $block->getPage()?->getId() !== $id) {
            return new JsonResponse(['error' => 'Block not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($block);
        $this->em->flush();

        if ($cms->getId() !== null) {
            $this->cmsPageCacheService->invalidatePage($cms->getId());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serializePage(Cms $cms): array
    {
        $titles = [];
        foreach ($cms->getTitles() as $title) {
            $titles[$title->getLanguage()] = $title->getTitle();
        }

        $linkNames = [];
        foreach ($cms->getLinkNames() as $linkName) {
            $linkNames[$linkName->getLanguage()] = $linkName->getName();
        }

        $menuLocations = [];
        foreach ($cms->getMenuLocations() as $menuLocation) {
            $menuLocations[] = $menuLocation->getLocation()?->value;
        }

        $blocks = [];
        foreach ($cms->getBlocks() as $block) {
            $blocks[] = $this->serializeBlock($block);
        }

        return [
            'id' => $cms->getId(),
            'slug' => $cms->getSlug(),
            'published' => $cms->isPublished(),
            'locked' => $cms->isLocked(),
            'titles' => $titles,
            'linkNames' => $linkNames,
            'menuLocations' => $menuLocations,
            'blocks' => $blocks,
        ];
    }

    private function serializeBlock(CmsBlock $block): array
    {
        return [
            'id' => $block->getId(),
            'language' => $block->getLanguage(),
            'type' => $block->getType()?->value,
            'priority' => $block->getPriority(),
            'json' => $block->getJson(),
        ];
    }

    private function updateTitles(Cms $cms, array $titlesData): void
    {
        $existing = [];
        foreach ($cms->getTitles() as $title) {
            $existing[$title->getLanguage()] = $title;
        }

        foreach ($titlesData as $lang => $titleText) {
            $lang = (string) $lang;
            if (isset($existing[$lang])) {
                $existing[$lang]->setTitle((string) $titleText);
                continue;
            }
            $title = new CmsTitle();
            $title->setLanguage($lang);
            $title->setTitle((string) $titleText);
            $cms->addTitle($title);
            $this->em->persist($title);
        }
    }

    private function updateLinkNames(Cms $cms, array $linkNamesData): void
    {
        $existing = [];
        foreach ($cms->getLinkNames() as $linkName) {
            $existing[$linkName->getLanguage()] = $linkName;
        }

        foreach ($linkNamesData as $lang => $nameText) {
            $lang = (string) $lang;
            if (isset($existing[$lang])) {
                $existing[$lang]->setName((string) $nameText);
                continue;
            }
            $linkName = new CmsLinkName();
            $linkName->setLanguage($lang);
            $linkName->setName((string) $nameText);
            $cms->addLinkName($linkName);
            $this->em->persist($linkName);
        }
    }

    private function updateMenuLocations(Cms $cms, array $locationValues): void
    {
        $existingByValue = [];
        foreach ($cms->getMenuLocations() as $menuLocation) {
            $existingByValue[$menuLocation->getLocation()?->value] = $menuLocation;
        }

        $newValues = array_map('intval', $locationValues);

        foreach ($existingByValue as $value => $menuLocation) {
            if (in_array($value, $newValues, true)) {
                continue;
            }

            $cms->removeMenuLocation($menuLocation);
            $this->em->remove($menuLocation);
        }

        foreach ($newValues as $locationValue) {
            if (isset($existingByValue[$locationValue])) {
                continue;
            }

            $location = MenuLocation::tryFrom($locationValue);
            if ($location !== null) {
                $menuLocation = new CmsMenuLocation();
                $menuLocation->setLocation($location);
                $cms->addMenuLocation($menuLocation);
                $this->em->persist($menuLocation);
            }
        }
    }
}
