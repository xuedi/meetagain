<?php declare(strict_types=1);

namespace Plugin\Photos\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\ListRegistry;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Item\TranslationFormHelper;
use App\Service\Seo\BreadcrumbBuilder;
use Plugin\Photos\Activity\Messages\PhotoAdded;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Form\PhotoAddType;
use Plugin\Photos\Form\PhotoEditType;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/photos')]
final class PhotoController extends AbstractController
{
    private const array TRANSLATED_FIELDS = ['title', 'description'];

    public function __construct(
        private readonly PhotoService $photoService,
        private readonly ConfigService $configService,
        private readonly ActivityService $activityService,
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly TagService $tagService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_photos_photolist', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Photos/photo/list.html.twig');
    }

    #[Route('/add', name: 'app_plugin_photos_photo_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(Request $request): Response
    {
        $this->denyUnlessUploadAllowed();

        $form = $this->createForm(PhotoAddType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $file = $form->get('photoFile')->getData();

            $photo = $file instanceof UploadedFile
                ? $this->photoService->create($file, $user, $this->translationFormHelper->extractTranslations($form, self::TRANSLATED_FIELDS))
                : null;

            if ($photo === null) {
                $this->addFlash('error', 'photos_photo.flash_upload_failed');

                return $this->render('@Photos/photo/add.html.twig', ['form' => $form]);
            }

            $this->tagService->setTags(PhotoService::ITEM_TYPE, (int) $photo->getId(), $this->assignmentFormHelper->extractAssignment($form));

            $this->activityService->log(PhotoAdded::TYPE, $user, [
                'photo_id' => $photo->getId(),
                'photo_title' => $photo->getAnyTranslatedTitle(),
            ]);
            $this->addFlash('success', 'photos_photo.flash_added');

            return $this->redirectToRoute('app_plugin_photos_photo_show', ['id' => $photo->getId()]);
        }

        return $this->render('@Photos/photo/add.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_plugin_photos_photo_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, ListRegistry $listRegistry, BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        if (!$listRegistry->has(PhotoService::ITEM_TYPE)) {
            throw $this->createNotFoundException();
        }

        $photo = $this->photoService->get($id);
        if ($photo === null) {
            throw $this->createNotFoundException('Photo not found');
        }

        return $this->render('@Photos/photo/detail.html.twig', [
            'photo' => $photo,
            'canManage' => $this->canManage($photo),
            'showCameraMeta' => $this->configService->getConfig()->isShowCameraMeta(),
            'breadcrumbs' => $breadcrumbBuilder->build('app_photos_photolist', 'photos.menu_main', $this->label($photo, $request->getLocale())),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_photos_photo_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, Request $request): Response
    {
        $photo = $this->photoService->getManaged($id);
        if ($photo === null) {
            throw $this->createNotFoundException('Photo not found');
        }
        $this->denyUnlessManager($photo);

        $form = $this->createForm(PhotoEditType::class, null, ['photo' => $photo]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->photoService->updateTranslations($photo, $this->translationFormHelper->extractTranslations($form, self::TRANSLATED_FIELDS));
            $this->tagService->setTags(PhotoService::ITEM_TYPE, (int) $photo->getId(), $this->assignmentFormHelper->extractAssignment($form));
            $this->addFlash('success', 'photos_photo.flash_updated');

            return $this->redirectToRoute('app_plugin_photos_photo_show', ['id' => $photo->getId()]);
        }

        return $this->render('@Photos/photo/edit.html.twig', [
            'form' => $form,
            'photo' => $photo,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_photos_photo_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id, Request $request): Response
    {
        $photo = $this->photoService->getManaged($id);
        if ($photo === null) {
            throw $this->createNotFoundException('Photo not found');
        }
        $this->denyUnlessManager($photo);

        if (!$this->isCsrfTokenValid('app_plugin_photos_photo_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->photoService->delete($photo);
        $this->addFlash('success', 'photos_photo.flash_deleted');

        return $this->redirectToRoute('app_photos_photolist');
    }

    private function denyUnlessUploadAllowed(): void
    {
        if (!$this->configService->getConfig()->isMemberUploads() && !$this->isGranted('ROLE_STEWARD')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyUnlessManager(Photo $photo): void
    {
        if (!$this->canManage($photo)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function canManage(Photo $photo): bool
    {
        if ($this->isGranted('ROLE_STEWARD')) {
            return true;
        }

        return $this->isGranted('ROLE_USER') && $this->photoService->isOwnedBy($photo, $this->getAuthedUser());
    }

    private function label(Photo $photo, string $locale): string
    {
        $title = $photo->getTranslatedTitle($locale);

        return $title === '' ? $this->translator->trans('photos.untitled') : $title;
    }
}
