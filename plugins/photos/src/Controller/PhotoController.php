<?php declare(strict_types=1);

namespace Plugin\Photos\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Entity\User;
use App\Item\ListRegistry;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Item\TranslationFormHelper;
use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\Service\Seo\BreadcrumbBuilder;
use Plugin\Photos\Activity\Messages\PhotoAdded;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Event\AssociationWriter;
use Plugin\Photos\Event\DateTagService;
use Plugin\Photos\Form\EventUploadType;
use Plugin\Photos\Form\PhotoAddType;
use Plugin\Photos\Form\PhotoEditType;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Form\FormInterface;
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

    private const int STRIP_LENGTH = 3;

    public function __construct(
        private readonly PhotoService $photoService,
        private readonly ConfigService $configService,
        private readonly ActivityService $activityService,
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly TagService $tagService,
        private readonly AssociationService $associations,
        private readonly DateTagService $dateTagService,
        private readonly AssociationWriter $associationWriter,
        private readonly ContestService $contestService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_photos_photolist', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Photos/photo/list.html.twig', [
            'streamsEnabled' => $this->configService->getConfig()->isMemberStreams(),
            'contestEnabled' => $this->contestService->isLive(),
        ]);
    }

    #[Route('/streams', name: 'app_plugin_photos_streams', methods: ['GET'])]
    public function memberStreams(ListRegistry $listRegistry, UserRepository $userRepository): Response
    {
        $this->denyUnlessStreamsLive($listRegistry);

        $counts = $this->photoService->getStreamAuthors();
        $users = $this->usersById(array_keys($counts), $userRepository);
        $streams = $this->photoService->getStreams(array_keys($users), self::STRIP_LENGTH);

        $authors = [];
        foreach ($users as $userId => $user) {
            $authors[] = [
                'user' => $user,
                'count' => $counts[$userId],
                'photos' => $streams[$userId],
            ];
        }

        return $this->render('@Photos/stream/index.html.twig', ['authors' => $authors]);
    }

    #[Route('/streams/{id}', name: 'app_plugin_photos_stream', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function memberStream(int $id, ListRegistry $listRegistry, UserRepository $userRepository, BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        $this->denyUnlessStreamsLive($listRegistry);

        $user = $userRepository->find($id);
        $photos = $user instanceof User ? $this->photoService->getStream($id) : [];
        if (!$user instanceof User || $photos === []) {
            throw $this->createNotFoundException('Stream not found');
        }

        return $this->render('@Photos/stream/show.html.twig', [
            'streamUser' => $user,
            'photos' => $photos,
            'breadcrumbs' => $breadcrumbBuilder->build('app_plugin_photos_streams', 'photos_stream.page_title_index', $user->getName()),
        ]);
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

    #[Route('/event/{id}/upload', name: 'app_plugin_photos_event_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function eventUpload(int $id, Request $request, EventFilterService $eventFilterService, EventRepository $eventRepository): Response
    {
        $this->denyUnlessUploadAllowed();
        $event = $eventRepository->find($id);
        if (!$this->configService->getConfig()->isEventBox() || !$eventFilterService->isEventAccessible($id) || !$event instanceof Event) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'photos_event.flash_upload_failed');

            return $this->redirectToRoute('app_event_details', ['id' => $id]);
        }

        $added = $this->storeEventPhotos($event, $form->get('files')->getData());
        $this->addFlash(
            $added === 0 ? 'error' : 'success',
            $added === 0
                ? $this->translator->trans('photos_event.flash_upload_failed')
                : $this->translator->trans('photos_event.flash_uploaded', ['%count%' => $added]),
        );

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }

    #[Route('/{id}', name: 'app_plugin_photos_photo_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        Request $request,
        ListRegistry $listRegistry,
        BreadcrumbBuilder $breadcrumbBuilder,
        EventFilterService $eventFilterService,
        EventRepository $eventRepository,
    ): Response
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
            'contest' => $this->contestPanel($photo),
            'event' => $this->eventOf((int) $photo->getId(), $eventFilterService, $eventRepository),
            'canManage' => $this->canManage($photo),
            'showCameraMeta' => $this->configService->getConfig()->isShowCameraMeta(),
            'breadcrumbs' => $breadcrumbBuilder->build('app_photos_photolist', 'photos.menu_main', $this->label($photo, $request->getLocale())),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_photos_photo_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, Request $request, EventFilterService $eventFilterService, EventRepository $eventRepository): Response
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
            $this->associationWriter->setEvent($photo, $this->selectedEvent($form, $eventFilterService, $eventRepository));
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

    /** @param list<UploadedFile>|null $files */
    private function storeEventPhotos(Event $event, ?array $files): int
    {
        $user = $this->getAuthedUser();
        $eventId = (int) $event->getId();

        $added = 0;
        foreach ($files ?? [] as $file) {
            $photo = $this->photoService->create($file, $user, []);
            if ($photo === null) {
                continue;
            }

            $this->associations->attach($eventId, PhotoService::ITEM_TYPE, (int) $photo->getId(), (int) $user->getId());
            $this->dateTagService->assign($event, (int) $photo->getId());
            $this->activityService->log(PhotoAdded::TYPE, $user, [
                'photo_id' => $photo->getId(),
                'photo_title' => $photo->getAnyTranslatedTitle(),
            ]);
            $added++;
        }

        return $added;
    }

    private function selectedEvent(FormInterface $form, EventFilterService $eventFilterService, EventRepository $eventRepository): ?Event
    {
        $eventId = $form->get(PhotoEditType::EVENT_FIELD)->getData();
        if (!is_numeric($eventId) || !$eventFilterService->isEventAccessible((int) $eventId)) {
            return null;
        }

        return $eventRepository->find((int) $eventId);
    }

    /** @return array{live: bool, owner: bool, remaining: int} */
    private function contestPanel(Photo $photo): array
    {
        $live = $this->contestService->isLive();
        $owner = $live && $this->isGranted('ROLE_USER') && $this->photoService->isOwnedBy($photo, $this->getAuthedUser());

        return [
            'live' => $live,
            'owner' => $owner,
            'remaining' => $owner ? $this->contestService->remainingFor((int) $photo->getCreatedBy()) : 0,
        ];
    }

    private function eventOf(int $photoId, EventFilterService $eventFilterService, EventRepository $eventRepository): ?Event
    {
        foreach ($this->associations->eventIdsForItem(PhotoService::ITEM_TYPE, $photoId) as $eventId) {
            $event = $eventFilterService->isEventAccessible($eventId) ? $eventRepository->find($eventId) : null;
            if ($event instanceof Event) {
                return $event;
            }
        }

        return null;
    }

    private function denyUnlessStreamsLive(ListRegistry $listRegistry): void
    {
        if (!$listRegistry->has(PhotoService::ITEM_TYPE) || !$this->configService->getConfig()->isMemberStreams()) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, User>
     */
    private function usersById(array $userIds, UserRepository $userRepository): array
    {
        $byId = [];
        foreach ($userRepository->findBy(['id' => $userIds]) as $user) {
            $byId[(int) $user->getId()] = $user;
        }

        $ordered = [];
        foreach ($userIds as $userId) {
            if (!isset($byId[$userId])) {
                continue;
            }

            $ordered[$userId] = $byId[$userId];
        }

        return $ordered;
    }

    private function denyUnlessUploadAllowed(): void
    {
        if (!$this->configService->canUpload()) {
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
