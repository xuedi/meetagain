<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminEventCancelled;
use App\Activity\Messages\AdminEventCreated;
use App\Activity\Messages\AdminEventEdited;
use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\EventType as EventTypeEnum;
use App\Enum\ImageType;
use App\Filter\Admin\Event\AdminEventListFilterService;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Config\LanguageService;
use App\Service\Event\EventService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/events')]
final class EventController extends AbstractController implements AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_event',
                    route: 'app_admin_event',
                    active: 'event',
                    role: 'ROLE_ORGANIZER',
                ),
            ],
            sectionPriority: 50,
        );
    }

    public function __construct(
        private readonly ImageService $imageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageService $languageService,
        private readonly EventTranslationRepository $eventTransRepo,
        private readonly EventService $eventService,
        private readonly EventRepository $repo,
        private readonly AdminEventListFilterService $eventFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly ActivityService $activityService,
        private readonly ImageLocationService $imageLocationService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_event')]
    public function list(Request $request): Response
    {
        $filterResult = $this->eventFilterService->getEventIdFilter();
        $eventIds = $filterResult->getEventIds();
        $allEvents = $this->repo->findAllForAdmin($eventIds);

        $hideAuto = $request->query->getBoolean('hide_auto');
        $typeFilter = $request->query->getInt('type') ?: null;
        $scheduleFilter = $request->query->getString('schedule');
        if (!in_array($scheduleFilter, ['onetime', 'series'], true)) {
            $scheduleFilter = 'all';
        }

        $events = array_values(array_filter(
            $allEvents,
            fn (Event $e) => $this->matchesFilters($e, $hideAuto, $typeFilter, $scheduleFilter),
        ));

        $canceledCount = 0;
        foreach ($events as $event) {
            if (!$event->isCanceled()) {
                continue;
            }
            $canceledCount++;
        }

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($events),
                $this->translator->trans('admin_event.summary_total'),
            )),
        ];
        if (count($events) !== count($allEvents)) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($allEvents),
                $this->translator->trans('admin_event.summary_total_all'),
            ));
        }
        if ($canceledCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $canceledCount,
                $this->translator->trans('admin_event.summary_canceled'),
            ));
        }

        $actions = [
            $this->buildHideAutoToggle($hideAuto, $typeFilter, $scheduleFilter),
            $this->buildTypeDropdown($allEvents, $hideAuto, $typeFilter, $scheduleFilter),
            $this->buildScheduleDropdown($allEvents, $hideAuto, $typeFilter, $scheduleFilter),
            new AdminTopActionButton(
                label: $this->translator->trans('admin_event.page_title_new'),
                target: $this->generateUrl('app_admin_event_add'),
                icon: 'plus',
            ),
        ];

        return $this->render('admin/event/list.html.twig', [
            'nextEvent' => $this->repo->getNextEventId($eventIds),
            'events' => $events,
            'rsvpCounts' => $this->repo->getRsvpCounts($eventIds),
            'active' => 'event',
            'adminTop' => new AdminTop(info: $info, actions: $actions),
        ]);
    }

    private function matchesFilters(Event $e, bool $hideAuto, ?int $typeFilter, string $scheduleFilter): bool
    {
        if ($hideAuto && $e->getRecurringOf() !== null) {
            return false;
        }
        if ($typeFilter !== null && $e->getType()?->value !== $typeFilter) {
            return false;
        }
        if ($scheduleFilter === 'onetime' && ($e->getRecurringOf() !== null || $e->getRecurringRule() !== null)) {
            return false;
        }
        if ($scheduleFilter === 'series' && $e->getRecurringOf() === null && $e->getRecurringRule() === null) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, int|string|bool> URL params preserving every active filter except $exclude.
     */
    private function preserveFiltersExcept(string $exclude, bool $hideAuto, ?int $typeFilter, string $scheduleFilter): array
    {
        $p = [];
        if ($exclude !== 'hide_auto' && $hideAuto) {
            $p['hide_auto'] = 1;
        }
        if ($exclude !== 'type' && $typeFilter !== null) {
            $p['type'] = $typeFilter;
        }
        if ($exclude !== 'schedule' && $scheduleFilter !== 'all') {
            $p['schedule'] = $scheduleFilter;
        }

        return $p;
    }

    private function buildHideAutoToggle(bool $hideAuto, ?int $typeFilter, string $scheduleFilter): AdminTopActionButton
    {
        $params = $this->preserveFiltersExcept('hide_auto', $hideAuto, $typeFilter, $scheduleFilter);
        if (!$hideAuto) {
            $params['hide_auto'] = 1;
        }

        return new AdminTopActionButton(
            label: $this->translator->trans(
                $hideAuto ? 'admin_event.button_show_auto' : 'admin_event.button_hide_auto',
            ),
            target: $this->generateUrl('app_admin_event', $params),
            icon: $hideAuto ? 'eye' : 'eye-slash',
        );
    }

    /**
     * @param array<Event> $allEvents
     */
    private function buildTypeDropdown(array $allEvents, bool $hideAuto, ?int $typeFilter, string $scheduleFilter): AdminTopActionDropdown
    {
        $countAll = count(array_filter(
            $allEvents,
            fn (Event $e) => $this->matchesFilters($e, $hideAuto, null, $scheduleFilter),
        ));

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_event.filter_type_any'),
                target: $this->generateUrl(
                    'app_admin_event',
                    $this->preserveFiltersExcept('type', $hideAuto, $typeFilter, $scheduleFilter),
                ),
                isActive: $typeFilter === null,
                count: $countAll,
            ),
        ];

        $activeLabel = $this->translator->trans('admin_event.filter_type_any');
        foreach (EventTypeEnum::cases() as $case) {
            $count = count(array_filter(
                $allEvents,
                fn (Event $e) => $this->matchesFilters($e, $hideAuto, $case->value, $scheduleFilter),
            ));
            $params = $this->preserveFiltersExcept('type', $hideAuto, $typeFilter, $scheduleFilter);
            $params['type'] = $case->value;
            $label = $this->translator->trans('admin_event.filter_type_' . strtolower($case->name));
            $isActive = $typeFilter === $case->value;
            if ($isActive) {
                $activeLabel = $label;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $label,
                target: $this->generateUrl('app_admin_event', $params),
                isActive: $isActive,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_event.filter_type_label'),
                $activeLabel,
            ),
            options: $options,
            icon: 'tag',
        );
    }

    /**
     * @param array<Event> $allEvents
     */
    private function buildScheduleDropdown(array $allEvents, bool $hideAuto, ?int $typeFilter, string $scheduleFilter): AdminTopActionDropdown
    {
        $values = ['all', 'onetime', 'series'];
        $options = [];
        $activeLabel = '';
        foreach ($values as $value) {
            $count = count(array_filter(
                $allEvents,
                fn (Event $e) => $this->matchesFilters($e, $hideAuto, $typeFilter, $value),
            ));
            $params = $this->preserveFiltersExcept('schedule', $hideAuto, $typeFilter, $scheduleFilter);
            if ($value !== 'all') {
                $params['schedule'] = $value;
            }
            $label = $this->translator->trans('admin_event.filter_schedule_' . $value);
            $isActive = $scheduleFilter === $value;
            if ($isActive) {
                $activeLabel = $label;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $label,
                target: $this->generateUrl('app_admin_event', $params),
                isActive: $isActive,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_event.filter_schedule_label'),
                $activeLabel,
            ),
            options: $options,
            icon: 'calendar',
        );
    }

    #[Route('/{id}/edit', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_UPDATE, $event);

        // Validate event is accessible in current context
        if (!$this->eventFilterService->isEventAccessible($event->getId())) {
            throw $this->createAccessDeniedException('This event is not accessible in the current context');
        }

        $form = $this->createForm(EventType::class, $event);

        // Only set form data on GET request (initial load)
        if ($request->isMethod('GET')) {
            $form->get('location')->setData($event->getLocation());
            $form->get('host')->setData($event->getHost());
        }

        // TODO: simplify with vanilla symfony components now the cascading flush effect is fixed
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            // overwrite basic data
            $event->setInitial(true);
            $event->setUser($user);

            // manually hydrate location (unmapped field)
            $locationData = $form->get('location')->getData();
            if ($locationData instanceof Location) {
                $event->setLocation($locationData);
            }

            // manually hydrate hosts (unmapped field)
            $event->getHost()->clear();
            $hostsData = $form->get('host')->getData();
            if (is_iterable($hostsData)) {
                foreach ($hostsData as $host) {
                    if (!$host instanceof Host) {
                        continue;
                    }

                    $event->addHost($host);
                }
            }

            // event image
            $image = null;
            $oldPreviewId = $event->getPreviewImage()?->getId();
            $imageData = $form->get('image')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->imageService->upload($imageData, $user, ImageType::EventTeaser);
            }
            if ($image instanceof Image) {
                $event->setPreviewImage($image);
            }

            // save translations
            foreach ($this->languageService->getAdminFilteredEnabledCodes() as $languageCode) {
                $translation = $this->getTranslation($languageCode, $event->getId());
                $translation->setEvent($event);
                $translation->setLanguage($languageCode);
                $translation->setTitle($form->get("title-{$languageCode}")->getData() ?? '');
                $translation->setTeaser($form->get("teaser-{$languageCode}")->getData() ?? '');
                $translation->setDescription($form->get("description-{$languageCode}")->getData() ?? '');

                $this->entityManager->persist($translation);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $this->activityService->log(AdminEventEdited::TYPE, $user, ['event_id' => $event->getId()]);
            $this->entityActionDispatcher->dispatch(EntityAction::UpdateEvent, $event->getId());

            // create thumbnail and update location index
            if ($image instanceof Image) {
                $this->imageService->createThumbnails($image, ImageType::EventTeaser);
                if ($oldPreviewId !== null) {
                    $this->imageLocationService->removeLocation($oldPreviewId, ImageType::EventTeaser, $event->getId());
                }
                $this->imageLocationService->addLocation($image->getId(), ImageType::EventTeaser, $event->getId());
            }

            if ($form->get('allFollowing')->getData() === true) {
                $cnt = $this->eventService->updateRecurringEvents($event);
                $this->addFlash('success', $this->translator->trans('admin_event.flash_saved_with_followup', ['%count%' => $cnt]));
            } else {
                $this->addFlash('success', $this->translator->trans('admin_event.flash_saved'));
            }

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/edit.html.twig', [
            'active' => 'event',
            'event' => $event,
            'form' => $form,
            'adminTop' => $this->buildBackOnlyTop(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_event_delete', methods: ['POST'])]
    public function delete(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_DELETE, $event);

        $this->addFlash('error', $this->translator->trans('admin_event.flash_delete_not_implemented'));

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_event_cancel', methods: ['POST'])]
    public function cancel(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CANCEL, $event);

        $user = $this->getAuthedUser();
        $rsvpCount = $event->getRsvp()->count();
        $this->eventService->cancelEvent($event);
        $this->activityService->log(AdminEventCancelled::TYPE, $user, ['event_id' => $event->getId()]);
        if ($rsvpCount > 0) {
            $this->addFlash('success', $this->translator->trans('admin_event.flash_canceled', ['%count%' => $rsvpCount]));
        }

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/uncancel', name: 'app_admin_event_uncancel', methods: ['POST'])]
    public function uncancel(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CANCEL, $event);

        $this->eventService->uncancelEvent($event);

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    private function getTranslation(mixed $languageCode, ?int $getId): EventTranslation
    {
        $translation = $this->eventTransRepo->findOneBy(['language' => $languageCode, 'event' => $getId]);
        if ($translation !== null) {
            return $translation;
        }

        return new EventTranslation();
    }

    #[Route('/new', name: 'app_admin_event_add', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CREATE);

        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->remove('createdAt');
        $form->remove('image');
        $form->remove('user');
        $form->remove('status');
        $form->remove('allFollowing');
        $form->remove('featured');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            $event->setCreatedAt(new DateTimeImmutable());
            $event->setPreviewImage(null);
            $event->setInitial(true);
            $event->setFeatured(false);
            $event->setUser($user);

            // manually hydrate location (unmapped field)
            $locationData = $form->get('location')->getData();
            if ($locationData instanceof Location) {
                $event->setLocation($locationData);
            }

            // manually hydrate hosts (unmapped field)
            $hostsData = $form->get('host')->getData();
            if (is_iterable($hostsData)) {
                foreach ($hostsData as $host) {
                    if (!$host instanceof Host) {
                        continue;
                    }

                    $event->addHost($host);
                }
            }

            $entityManager->persist($event);
            $entityManager->flush();

            $this->activityService->log(AdminEventCreated::TYPE, $user, ['event_id' => $event->getId()]);
            $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, $event->getId());

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/new.html.twig', [
            'active' => 'event',
            'location' => $event,
            'form' => $form,
            'adminTop' => $this->buildBackOnlyTop(),
        ]);
    }

    private function buildBackOnlyTop(): AdminTop
    {
        return new AdminTop(
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_event'),
                    icon: 'arrow-left',
                ),
            ],
        );
    }

    private function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                'Should never happen, see: config/packages/security.yaml',
            );
        }

        return $user;
    }
}
