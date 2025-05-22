<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Image;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Service\TranslationService;
use App\Service\UploadService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminEventController extends AbstractController
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationService $translationService,
        private readonly EventTranslationRepository $eventTransRepo,
    ) {
    }

    #[Route('/admin/event/', name: 'app_admin_event')]
    public function eventList(EventRepository $repo): Response
    {
        return $this->render('admin/event/list.html.twig', [
            'events' => $repo->findBy([], ['start' => 'ASC']),
        ]);
    }

    #[Route('/admin/event/{id}/edit', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function eventEdit(Request $request, Event $event): Response
    {

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // overwrite basic data
            $event->setInitial(true);
            $event->setUser($this->getUser());

            // event image
            $image = null;
            $imageData = $form->get('image')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->uploadService->upload($imageData, $this->getUser());
            }
            if ($image instanceof Image) {
                $event->setPreviewImage($image); // TODO: add source for image creation
            }

            // save translations
            foreach ($this->translationService->getLanguageCodes() as $languageCode) {
                $translation = $this->getTranslation($languageCode, $event->getId());
                $translation->setEvent($event);
                $translation->setLanguage($languageCode);
                $translation->setTitle($form->get("title-$languageCode")->getData());
                $translation->setDescription($form->get("description-$languageCode")->getData());

                $this->entityManager->persist($translation);
            }

            // persist
            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // create thumbnail
            if ($image instanceof Image) {
                $this->uploadService->createThumbnails($image, [[600, 400]]);
            }
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/admin/event/{id}/delete', name: 'app_admin_event_delete')]
    public function eventDelete(EventRepository $repo): Response
    {
        dump('delete');
        exit;
    }
    private function getTranslation(mixed $languageCode, ?int $getId): EventTranslation
    {
        $translation = $this->eventTransRepo->findOneBy(['language' => $languageCode, 'event' => $getId]);
        if ($translation !== null) {
            return $translation;
        }
        return new EventTranslation();
    }

    #[Route('/admin/event/add', name: 'app_admin_event_add', methods: ['GET', 'POST'])]
    public function eventAdd(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->remove('createdAt');
        $form->remove('image');
        $form->remove('user');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setPreviewImage(null);
            $event->setInitial(true);
            $event->setUser($this->getUser());

            $entityManager->persist($event);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/new.html.twig', [
            'location' => $event,
            'form' => $form,
        ]);
    }
}
