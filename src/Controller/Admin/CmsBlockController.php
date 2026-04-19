<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Exception\BlockValidationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;
use App\Filter\Admin\Cms\AdminCmsListFilterService;
use App\Form\EventUploadType;
use App\Repository\CmsBlockRepository;
use App\Repository\CmsRepository;
use App\Service\Cms\CmsBlockService;
use App\Service\Cms\CmsPageCacheService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/cms')]
final class CmsBlockController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly CmsRepository $cmsRepo,
        private readonly EntityManagerInterface $em,
        private readonly CmsBlockRepository $blockRepo,
        private readonly CmsBlockService $blockService,
        private readonly AdminCmsListFilterService $adminCmsListFilterService,
        private readonly CmsPageCacheService $cmsPageCacheService,
        private readonly LoggerInterface $logger,
        private readonly ImageService $imageService,
        private readonly ValidatorInterface $validator,
        private readonly ImageLocationService $imageLocationService,
    ) {}

    #[Route('/block/{blockId}/edit', name: 'app_admin_cms_block_edit', methods: ['GET'])]
    public function cmsBlockEdit(int $blockId): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw $this->createNotFoundException('Block not found');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($block->getPage()->getId())) {
            $this->logger->warning('CMS block edit access denied', ['blockId' => $blockId]);
            throw $this->createAccessDeniedException('This CMS block is not accessible in the current context');
        }

        return $this->render('admin/cms/cms_block_edit.html.twig', [
            'active' => 'cms',
            'block' => $block,
            'blockObject' => $block->getBlockObject(),
            'blockImage' => $block->getImage(),
            'cms' => $block->getPage(),
        ]);
    }

    #[Route('/block/{blockId}/image/toggle-side', name: 'app_admin_cms_block_image_toggle_side', methods: ['GET'])]
    public function cmsBlockImageToggleSide(int $blockId): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw $this->createNotFoundException('Block not found');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($block->getPage()->getId())) {
            throw $this->createAccessDeniedException('This CMS block is not accessible in the current context');
        }

        if (!$block->getType()->getCapabilities()->supportsImageRight) {
            throw $this->createAccessDeniedException('Block type does not support imageRight');
        }

        $json = $block->getJson();
        $json['imageRight'] = !($json['imageRight'] ?? false);
        $block->setJson($json);
        $this->em->persist($block);
        $this->em->flush();

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
    }

    #[Route('/block/{blockId}/image/remove', name: 'app_admin_cms_block_image_remove', methods: ['GET'])]
    public function cmsBlockImageRemove(int $blockId): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw $this->createNotFoundException('Block not found');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($block->getPage()->getId())) {
            throw $this->createAccessDeniedException('This CMS block is not accessible in the current context');
        }

        if (!$block->getType()->getCapabilities()->supportsImage()) {
            throw $this->createAccessDeniedException('Block type does not support images');
        }

        $oldImageId = $block->getImage()?->getId();
        $block->setImage(null);
        $this->em->persist($block);
        $this->em->flush();

        if ($oldImageId !== null) {
            $this->imageLocationService->removeLocation($oldImageId, ImageType::CmsBlock, $blockId);
        }

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
    }

    #[Route('/block/{id}/add', name: 'app_admin_cms_add_block', methods: ['POST'])]
    public function cmsBlockAdd(Request $request, int $id): Response
    {
        $cmsPage = $this->cmsRepo->find($id);
        if ($cmsPage === null) {
            throw new RuntimeException('Could not find valid page');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($cmsPage->getId())) {
            throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
        }

        $locale = $request->request->getString('editLocale');
        $blockType = CmsBlockType::from((int) $request->request->get('blockType'));

        try {
            $this->blockService->createBlock($cmsPage, $locale, $blockType, $request->getPayload()->all());
            $this->cmsPageCacheService->invalidatePage($id);
        } catch (BlockValidationException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

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
        $this->cmsPageCacheService->invalidatePage($pageId);

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
        $this->cmsPageCacheService->invalidatePage($pageId);

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $pageId,
            'locale' => $locale,
        ]);
    }

    #[Route('/block/save', name: 'app_admin_cms_edit_block_save', methods: ['POST'])]
    public function cmsBlockSave(Request $request): Response
    {
        $blockId = (int) $request->request->get('blockId');
        $type = CmsBlockType::from((int) $request->request->get('blockType'));

        try {
            $block = $this->blockService->updateBlock($blockId, $type, $request->getPayload()->all());
            $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());
        } catch (BlockValidationException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
    }

    #[Route('/block/delete', name: 'app_admin_cms_block_delete', methods: ['GET'])]
    public function cmsBlockDelete(Request $request): Response
    {
        $this->blockService->deleteBlock((int) $request->query->get('blockId'));
        $this->cmsPageCacheService->invalidatePage((int) $request->query->get('id'));

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $request->query->get('id'),
            'locale' => $request->query->get('locale'),
        ]);
    }

    #[Route('/block/{blockId}/card/{slot}/image/modal', name: 'app_admin_cms_card_image_modal', methods: ['GET'], requirements: ['slot' => '[0-2]'])]
    public function cmsCardImageModal(int $blockId, int $slot): Response
    {
        $form = $this->createForm(EventUploadType::class, null, [
            'action' => $this->generateUrl('app_admin_cms_card_image_upload', [
                'blockId' => $blockId,
                'slot' => $slot,
            ]),
        ]);

        return new Response($this->renderView('admin/cms/gallery_upload_modal.html.twig', [
            'form' => $form,
        ]));
    }

    #[Route('/block/{blockId}/card/{slot}/image/upload', name: 'app_admin_cms_card_image_upload', methods: ['POST'], requirements: ['slot' => '[0-2]'])]
    public function cmsCardImageUpload(Request $request, int $blockId, int $slot): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not find block');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($block->getPage()->getId())) {
            throw $this->createAccessDeniedException('This CMS block is not accessible in the current context');
        }

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $user = $this->getUser();
            assert($user instanceof User);
            $fileConstraint = new File(maxSize: '10M', mimeTypes: ['image/*']);

            $files = $form->get('files')->getData() ?? [];
            $file = reset($files);
            if ($file instanceof UploadedFile) {
                $violations = $this->validator->validate($file, $fileConstraint);
                if (count($violations) > 0) {
                    $this->addFlash('danger', 'Invalid file: ' . (string) $violations->get(0)->getMessage());
                    return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
                }

                $image = $this->imageService->upload($file, $user, ImageType::CmsCardImage);
                $image->setUploader($user);
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($image);
                $this->em->flush();
                $this->imageService->createThumbnails($image, ImageType::CmsCardImage);

                $json = $block->getJson();
                $oldCardImageId = $json['cards'][$slot]['image']['id'] ?? null;
                $json['cards'][$slot]['image'] = ['id' => $image->getId(), 'hash' => $image->getHash()];
                $block->setJson($json);
                $this->em->persist($block);
                $this->em->flush();

                if ($oldCardImageId !== null) {
                    $this->imageLocationService->removeLocation((int) $oldCardImageId, ImageType::CmsCardImage, $blockId);
                }
                $this->imageLocationService->addLocation($image->getId(), ImageType::CmsCardImage, $blockId);
            }
        }

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
    }

    #[Route('/block/{blockId}/card/{slot}/image/remove', name: 'app_admin_cms_card_image_remove', methods: ['GET'], requirements: ['slot' => '[0-2]'])]
    public function cmsCardImageRemove(int $blockId, int $slot): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not find block');
        }

        if (!$this->adminCmsListFilterService->isCmsAccessible($block->getPage()->getId())) {
            throw $this->createAccessDeniedException('This CMS block is not accessible in the current context');
        }

        $json = $block->getJson();
        if (isset($json['cards'][$slot])) {
            $oldCardImageId = $json['cards'][$slot]['image']['id'] ?? null;
            $json['cards'][$slot]['image'] = null;
            $block->setJson($json);
            $this->em->persist($block);
            $this->em->flush();

            if ($oldCardImageId !== null) {
                $this->imageLocationService->removeLocation((int) $oldCardImageId, ImageType::CmsCardImage, $blockId);
            }
        }

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_block_edit', ['blockId' => $blockId]);
    }

    #[Route('/block/{blockId}/gallery/modal', name: 'app_admin_cms_gallery_modal', methods: ['GET'])]
    public function cmsGalleryModal(int $blockId): Response
    {
        $form = $this->createForm(EventUploadType::class, null, [
            'action' => $this->generateUrl('app_admin_cms_gallery_add', ['blockId' => $blockId]),
        ]);

        return new Response($this->renderView('admin/cms/gallery_upload_modal.html.twig', [
            'form' => $form,
        ]));
    }

    #[Route('/block/{blockId}/gallery/add', name: 'app_admin_cms_gallery_add', methods: ['POST'])]
    public function cmsGalleryAdd(Request $request, int $blockId): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not find block');
        }

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $user = $this->getUser();
            assert($user instanceof User);
            $fileConstraint = new File(maxSize: '10M', mimeTypes: ['image/*']);
            foreach ($form->get('files')->getData() ?? [] as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $violations = $this->validator->validate($file, $fileConstraint);
                if (count($violations) > 0) {
                    $this->logger->warning('Skipping invalid file during gallery upload', [
                        'error' => $violations->get(0)->getMessage(),
                        'file' => $file->getClientOriginalName(),
                    ]);
                    continue;
                }
                $image = $this->imageService->upload($file, $user, ImageType::CmsGallery);
                $image->setUploader($user);
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($image);
                $this->em->flush();
                $this->imageService->createThumbnails($image, ImageType::CmsGallery);

                $json = $block->getJson();
                $json['images'][] = ['id' => $image->getId(), 'hash' => $image->getHash()];
                $block->setJson($json);
                $this->em->persist($block);
                $this->em->flush();

                $this->imageLocationService->addLocation($image->getId(), ImageType::CmsGallery, $blockId);
            }
        }

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $block->getPage()->getId(),
            'locale' => $block->getLanguage(),
        ]);
    }

    #[Route('/block/{blockId}/gallery/remove/{imageId}', name: 'app_admin_cms_gallery_remove', methods: ['GET'])]
    public function cmsGalleryRemove(int $blockId, int $imageId): Response
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not find block');
        }

        $json = $block->getJson();
        $json['images'] = array_values(array_filter(
            $json['images'] ?? [],
            static fn(array $item) => $item['id'] !== $imageId,
        ));
        $block->setJson($json);
        $this->em->persist($block);
        $this->em->flush();

        $this->imageLocationService->removeLocation($imageId, ImageType::CmsGallery, $blockId);

        $this->cmsPageCacheService->invalidatePage($block->getPage()->getId());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $block->getPage()->getId(),
            'locale' => $block->getLanguage(),
        ]);
    }
}
