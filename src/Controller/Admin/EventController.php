<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminEventCancelled;
use App\Activity\Messages\AdminEventCreated;
use App\Activity\Messages\AdminEventEdited;
use App\Entity\AdminLink;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\Location;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ImageType;
use App\Filter\Admin\Event\AdminEventListFilterService;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Service\Config\LanguageService;
use App\Service\Event\EventService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/events')]
final class EventController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'Content',
            links: [
                new AdminLink(
                    label: 'menu_admin_event',
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
    ) {}

    #[Route('', name: 'app_admin_event')]
    public function list(): Response
    {
        // Apply multisite filtering if enabled
        $filterResult = $this->eventFilterService->getEventIdFilter();
        $eventIds = $filterResult->getEventIds();

        return $this->render('admin/event/list.html.twig', [
            'nextEvent' => $this->repo->getNextEventId($eventIds),
            'events' => $this->repo->findAllForAdmin($eventIds),
            'rsvpCounts' => $this->repo->getRsvpCounts($eventIds),
            'active' => 'event',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event): Response
    {
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
                $translation->setTitle($form->get("title-$languageCode")->getData() ?? '');
                $translation->setTeaser($form->get("teaser-$languageCode")->getData() ?? '');
                $translation->setDescription($form->get("description-$languageCode")->getData() ?? '');

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

            $followUp = '';
            if ($form->get('allFollowing')->getData() === true) {
                $cnt = $this->eventService->updateRecurringEvents($event);
                $followUp = " & updated $cnt follow-up events.";
            }

            $this->addFlash('success', 'Event saved' . $followUp);

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/edit.html.twig', [
            'active' => 'event',
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_event_delete', methods: ['POST'])]
    public function delete(Event $event): Response
    {
        $this->addFlash('error', 'Event deletion is not yet implemented.');

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_event_cancel', methods: ['POST'])]
    public function cancel(Event $event): Response
    {
        $user = $this->getAuthedUser();
        $rsvpCount = $event->getRsvp()->count();
        $this->eventService->cancelEvent($event);
        $this->activityService->log(AdminEventCancelled::TYPE, $user, ['event_id' => $event->getId()]);
        if ($rsvpCount > 0) {
            $this->addFlash('success', "Event canceled. $rsvpCount user(s) have been notified.");
        }

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/uncancel', name: 'app_admin_event_uncancel', methods: ['POST'])]
    public function uncancel(Event $event): Response
    {
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
        ]);
    }
}
