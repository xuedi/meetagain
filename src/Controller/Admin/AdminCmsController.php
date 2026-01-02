<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Image;
use App\Entity\BlockType\Paragraph;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\Title;
use App\Entity\Cms;
use App\Entity\CmsBlockTypes;
use App\Entity\User;
use App\Form\CmsType;
use App\Repository\AnnouncementRepository;
use App\Repository\CmsBlockRepository;
use App\Repository\CmsRepository;
use App\Service\CmsBlockService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminCmsController extends AbstractController
{
    public function __construct(
        private readonly CmsRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly CmsBlockRepository $blockRepo,
        private readonly CmsBlockService $blockService,
        private readonly AnnouncementRepository $announcementRepo,
    ) {
    }

    #[Route('/admin/cms/', name: 'app_admin_cms')]
    public function cmsList(): Response
    {
        $newForm = $this->createForm(CmsType::class, null, [
            'action' => $this->generateUrl('app_admin_cms_add'),
        ]);

        return $this->render('admin/cms/list.html.twig', [
            'active' => 'cms',
            'form' => $newForm,
            'cms' => $this->repo->findAll(),
        ]);
    }

    #[Route(
        '/admin/cms/{id}/edit/{locale}/{blockId}',
        name: 'app_admin_cms_edit',
        methods: ['GET', 'POST'],
    )]
    public function cmsEdit(Request $request, Cms $cms, ?string $locale = null, ?int $blockId = null): Response
    {
        $locale = $this->getLastEditLocale($locale, $request->getSession());

        $form = $this->createForm(CmsType::class, $cms);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_admin_cms');
        }

        $newBlocks = [
            Headline::getType(),
            Paragraph::getType(),
            Text::getType(),
            Image::getType(),
            Hero::getType(),
            EventTeaser::getType(),
            Title::getType(),
        ];

        return $this->render('admin/cms/edit.html.twig', [
            'active' => 'cms',
            'newBlocks' => $newBlocks,
            'editLocale' => $locale,
            'editBlock' => $blockId,
            'blocks' => $this->blockRepo->getBlocks($cms->getId(), $locale),
            'form' => $form,
            'cms' => $cms,
            'linkedAnnouncement' => $this->announcementRepo->findByCmsPage($cms->getId()),
        ]);
    }

    #[Route('/admin/cms/delete', name: 'app_admin_cms_delete', methods: ['GET'])]
    public function cmsDelete(Request $request): Response
    {
        $id = $request->query->get('id');
        $cmsPage = $this->repo->find($id);
        if ($cmsPage !== null) {
            $this->em->remove($cmsPage);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_cms');
    }

    #[Route('/admin/cms/add', name: 'app_admin_cms_add', methods: ['POST'])]
    public function cmsAdd(Request $request): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);

        $newPage = new Cms();
        $newPage->setSlug($request->request->all('cms')['slug']);
        $newPage->setPublished(false);
        $newPage->setCreatedBy($user);
        $newPage->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($newPage);
        $this->em->flush();

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $newPage->getId(),
            'locale' => $request->getLocale(),
        ]);
    }

    #[Route('/admin/cms/block/{id}/add', name: 'app_admin_cms_add_block', methods: ['POST'])]
    public function cmsBlockAdd(Request $request, int $id): Response
    {
        $cmsPage = $this->repo->find($id);
        if ($cmsPage === null) {
            throw new RuntimeException('Could not find valid page');
        }

        $locale = $request->request->get('editLocale');
        $blockType = CmsBlockTypes::from((int) $request->request->get('blockType'));

        $this->blockService->createBlock($cmsPage, $locale, $blockType, $request->getPayload()->all());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $id,
            'locale' => $locale,
        ]);
    }

    #[Route('/admin/cms/block/down', name: 'app_admin_cms_edit_block_down', methods: ['GET'])]
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

    #[Route('/admin/cms/block/up', name: 'app_admin_cms_edit_block_up', methods: ['GET'])]
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

    #[Route('/admin/cms/block/save', name: 'app_admin_cms_edit_block_save', methods: ['POST'])]
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

    #[Route('/admin/cms/block/delete', name: 'app_admin_cms_block_delete', methods: ['GET'])]
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
            $locale = $session->get($lastEditLocaleKey, 'en');
        }
        $session->set($lastEditLocaleKey, $locale);

        return $locale;
    }
}
