<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Service\EventService;
use App\Service\ImageService;
use App\Service\TranslationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EventController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationService $translationService,
        private readonly EventTranslationRepository $eventTransRepo,
        private readonly EventService $eventService,
        private readonly EventRepository $repo,
    ) {}

    public function eventList(): Response
    {
        return $this->render('admin_modules/tables/event_list.html.twig', [
            'nextEvent' => $this->repo->getNextEventId(),
            'events' => $this->repo->findBy([], ['start' => 'ASC']),
            'active' => 'event',
        ]);
    }

    public function eventEdit(Request $request, Event $event): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            // overwrite basic data
            $event->setInitial(true);
            $event->setUser($user);

            // event image
            $image = null;
            $imageData = $form->get('image')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->imageService->upload($imageData, $user, ImageType::EventTeaser);
            }
            if ($image instanceof Image) {
                $event->setPreviewImage($image);
            }

            // save translations
            foreach ($this->translationService->getLanguageCodes() as $languageCode) {
                $translation = $this->getTranslation($languageCode, $event->getId());
                $translation->setEvent($event);
                $translation->setLanguage($languageCode);
                $translation->setTitle($form->get("title-$languageCode")->getData());
                $translation->setTeaser($form->get("teaser-$languageCode")->getData());
                $translation->setDescription($form->get("description-$languageCode")->getData());

                $this->entityManager->persist($translation);
            }

            // persist
            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // create thumbnail
            if ($image instanceof Image) {
                $this->imageService->createThumbnails($image);
            }

            $followUp = '';
            if ($form->get('allFollowing')->getData() === true) {
                $cnt = $this->eventService->updateRecurringEvents($event);
                $followUp = " & updated $cnt follow-up events.";
            }

            $this->addFlash('success', 'Event saved' . $followUp);
        }

        return $this->render('admin_modules/tables/event_edit.html.twig', [
            'active' => 'event',
            'event' => $event,
            'form' => $form,
        ]);
    }

    public function eventDelete(): Response
    {
        dump('delete');
        exit();
    }

    public function eventCancel(Event $event): Response
    {
        $rsvpCount = $event->getRsvp()->count();
        $this->eventService->cancelEvent($event);
        if ($rsvpCount > 0) {
            $this->addFlash('success', "Event canceled. $rsvpCount user(s) have been notified.");
        }

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    public function eventUncancel(Event $event): Response
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

    public function eventAdd(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->remove('createdAt');
        $form->remove('image');
        $form->remove('user');
        $form->remove('published');
        $form->remove('allFollowing');
        $form->remove('featured');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            $event->setCreatedAt(new DateTimeImmutable());
            $event->setPreviewImage(null);
            $event->setInitial(true);
            $event->setPublished(false);
            $event->setFeatured(false);
            $event->setUser($user);

            $entityManager->persist($event);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin_modules/tables/event_new.html.twig', [
            'active' => 'event',
            'location' => $event,
            'form' => $form,
        ]);
    }
}
