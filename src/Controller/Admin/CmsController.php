<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Image;
use App\Entity\BlockType\Paragraph;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\Title;
use App\Entity\Cms;
use App\Entity\CmsBlockTypes;
use App\Enum\EntityAction;
use App\Filter\Admin\Cms\AdminCmsListFilterService;
use App\Form\CmsType;
use App\Repository\AnnouncementRepository;
use App\Repository\CmsBlockRepository;
use App\Repository\CmsRepository;
use App\Service\CmsBlockService;
use App\Service\EntityActionDispatcher;
use App\Service\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOUNDER'), Route('/admin/cms')]
class CmsController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'System',
            links: [
                new AdminLink(label: 'CMS', route: 'app_admin_cms', active: 'cms', role: 'ROLE_FOUNDER'),
            ],
            sectionPriority: 100,
        );
    }

    public function __construct(
        private readonly CmsRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly CmsBlockRepository $blockRepo,
        private readonly CmsBlockService $blockService,
        private readonly AnnouncementRepository $announcementRepo,
        private readonly AdminCmsListFilterService $adminCmsListFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly LoggerInterface $logger,
        private readonly LanguageService $languageService,
    ) {}

    #[Route('', name: 'app_admin_cms')]
    public function cmsList(): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $newForm = $this->createForm(CmsType::class, null, [
            'action' => $this->generateUrl('app_admin_cms_add'),
            'is_admin' => $isAdmin,
        ]);

        // Apply admin-specific CMS list filtering
        $filterResult = $this->adminCmsListFilterService->getCmsIdFilter();
        $cmsPages = $this->repo->findByIds($filterResult->getCmsIds());

        // Get CMS IDs that have linked announcements
        $cmsIdsWithAnnouncements = [];
        foreach ($cmsPages as $page) {
            $announcement = $this->announcementRepo->findByCmsPage($page->getId());
            if ($announcement !== null) {
                $cmsIdsWithAnnouncements[] = $page->getId();
            }
        }

        return $this->render('admin/cms/cms_list.html.twig', [
            'active' => 'cms',
            'form' => $newForm,
            'cms' => $cmsPages,
            'is_admin' => $isAdmin,
            'cms_with_announcements' => $cmsIdsWithAnnouncements,
        ]);
    }

    #[Route(
        '/{id}/edit/{locale}/{blockId}',
        name: 'app_admin_cms_edit',
        requirements: ['locale' => '[^/]+', 'blockId' => '\d+'],
        defaults: ['locale' => null, 'blockId' => null],
        methods: ['GET', 'POST'],
    )]
    public function cmsEdit(Request $request, Cms $cms, ?string $locale = null, ?int $blockId = null): Response
    {
        // Validate CMS is accessible in current admin context
        if (!$this->adminCmsListFilterService->isCmsAccessible($cms->getId())) {
            $this->logAccessDenied($cms, $request, 'edit');
            throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
        }

        $locale = $this->getLastEditLocale($locale, $request->getSession());

        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(CmsType::class, $cms, [
            'is_admin' => $isAdmin,
            'edit_locale' => $locale,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::UpdateCms, $cms->getId());

            return $this->redirectToRoute('app_admin_cms_edit', [
                'id' => $cms->getId(),
                'locale' => $locale,
            ]);
        }

        $newBlocks = [
            Headline::getType(),
            Paragraph::getType(),
            Text::getType(),
            Image::getType(),
            Hero::getType(),
            EventTeaser::getType(),
        ];

        return $this->render('admin/cms/cms_edit.html.twig', [
            'active' => 'cms',
            'newBlocks' => $newBlocks,
            'editLocale' => $locale,
            'editBlock' => $blockId,
            'blocks' => $this->blockRepo->getBlocks($cms->getId(), $locale),
            'form' => $form,
            'cms' => $cms,
            'linkedAnnouncement' => $this->announcementRepo->findByCmsPage($cms->getId()),
            'is_admin' => $isAdmin,
        ]);
    }

    #[Route('/delete', name: 'app_admin_cms_delete', methods: ['GET'])]
    public function cmsDelete(Request $request): Response
    {
        $id = $request->query->get('id');
        $cmsPage = $this->repo->find($id);
        if ($cmsPage !== null) {
            // Validate CMS is accessible in current admin context
            if (!$this->adminCmsListFilterService->isCmsAccessible($cmsPage->getId())) {
                $this->logAccessDenied($cmsPage, $request, 'delete');
                throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
            }

            $cmsId = $cmsPage->getId();
            $this->em->remove($cmsPage);
            $this->em->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::DeleteCms, $cmsId);
        }

        return $this->redirectToRoute('app_admin_cms');
    }

    #[Route('/add', name: 'app_admin_cms_add', methods: ['POST'])]
    public function cmsAdd(Request $request): Response
    {
        $user = $this->getUser();

        $newPage = new Cms();
        $newPage->setSlug($request->request->all('cms')['slug']);
        $newPage->setPublished(false);
        $newPage->setLocked(false);
        $newPage->setCreatedBy($user);
        $newPage->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($newPage);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::CreateCms, $newPage->getId());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $newPage->getId(),
            'locale' => $request->getLocale(),
        ]);
    }

    #[Route('/block/{id}/add', name: 'app_admin_cms_add_block', methods: ['POST'])]
    public function cmsBlockAdd(Request $request, int $id): Response
    {
        $cmsPage = $this->repo->find($id);
        if ($cmsPage === null) {
            throw new RuntimeException('Could not find valid page');
        }

        // Validate CMS is accessible in current admin context
        if (!$this->adminCmsListFilterService->isCmsAccessible($cmsPage->getId())) {
            $this->logAccessDenied($cmsPage, $request, 'add_block');
            throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
        }

        $locale = $request->request->get('editLocale');
        $blockType = CmsBlockTypes::from((int) $request->request->get('blockType'));

        $this->blockService->createBlock($cmsPage, $locale, $blockType, $request->getPayload()->all());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $id,
            'locale' => $locale,
        ]);
    }

    #[Route('/block/down', name: 'app_admin_cms_edit_block_down', methods: ['GET'])]
    public function cmsBlockMoveDown(Request $request): Response
    {
        $pageId = (int) $request->query->get('id');
        $blockId = (int) $request->query->get('blockId');
        $locale = $request->query->get('locale');

        $this->blockService->moveBlockDown($pageId, $blockId, $locale);

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $pageId,
            'locale' => $locale,
        ]);
    }

    #[Route('/block/up', name: 'app_admin_cms_edit_block_up', methods: ['GET'])]
    public function cmsBlockMoveUp(Request $request): Response
    {
        $pageId = (int) $request->query->get('id');
        $blockId = (int) $request->query->get('blockId');
        $locale = $request->query->get('locale');

        $this->blockService->moveBlockUp($pageId, $blockId, $locale);

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $pageId,
            'locale' => $locale,
        ]);
    }

    #[Route('/block/save', name: 'app_admin_cms_edit_block_save', methods: ['POST'])]
    public function cmsBlockSave(Request $request): Response
    {
        $blockId = (int) $request->request->get('blockId');
        $type = CmsBlockTypes::from((int) $request->request->get('blockType'));

        $this->blockService->updateBlock($blockId, $type, $request->getPayload()->all());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $request->request->get('id'),
            'locale' => $request->request->get('locale'),
        ]);
    }

    #[Route('/block/delete', name: 'app_admin_cms_block_delete', methods: ['GET'])]
    public function cmsBlockDelete(Request $request): Response
    {
        $this->blockService->deleteBlock((int) $request->query->get('blockId'));

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $request->query->get('id'),
            'locale' => $request->query->get('locale'),
        ]);
    }

    private function getLastEditLocale(?string $locale, SessionInterface $session): string
    {
        $lastEditLocaleKey = 'lastEditLocale';
        if ($locale === null) {
            $locale = $session->get($lastEditLocaleKey, $this->languageService->getAdminFilteredEnabledCodes()[0]);
        }
        $session->set($lastEditLocaleKey, $locale);

        return $locale;
    }

    private function logAccessDenied(Cms $cms, Request $request, string $action): void
    {
        $filterResult = $this->adminCmsListFilterService->getCmsIdFilter();
        $allowedIds = $filterResult->hasActiveFilter() ? $filterResult->getCmsIds() : null;

        $context = [
            'action' => $action,
            'cms_id' => $cms->getId(),
            'cms_slug' => $cms->getSlug(),
            'request_uri' => $request->getUri(),
            'request_host' => $request->getHost(),
            'user_id' => $this->getUser()?->getId(),
            'allowed_cms_ids' => $allowedIds !== null ? array_slice($allowedIds, 0, 20) : 'all',
            'total_allowed' => $allowedIds !== null ? count($allowedIds) : null,
        ];

        // Add filter context for debugging (provided by filters like GroupContextFilter)
        $filterContext = $this->adminCmsListFilterService->getDebugContext($cms->getId());
        if ($filterContext !== []) {
            $context['filter_context'] = $filterContext;
        }

        $this->logger->warning('CMS page access denied', $context);
    }
}
