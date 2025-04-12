<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Image;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/event')]
class AdminEventController extends AbstractController
{
    #[Route('/', name: 'app_admin_event')]
    public function eventList(EventRepository $repo): Response
    {
        return $this->render('admin/event/list.html.twig', [
            'events' => $repo->findBy([], ['start' => 'ASC']),
        ]);
    }

    #[Route('/{id}/{locale}', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function eventEdit(
        Request $request,
        Event $event,
        UploadService $uploadService,
        EntityManagerInterface $entityManager,
        string $locale = 'en',
    ): Response {

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $uploadService->upload($form, 'image', $this->getUser());
            $event->setInitial(true);
            $event->setUser($this->getUser());
            if ($image instanceof Image) {
                $event->setPreviewImage($image);
            }

            $entityManager->persist($event);
            $entityManager->flush();
            if ($image instanceof Image) {
                $uploadService->createThumbnails($image, [[600, 400]]);
            }

            return $this->redirectToRoute('app_admin_event_edit', [
                'editLocale' => $locale,
                'id' => $event->getId(),
            ]);
        }

        return $this->render('admin/event/edit.html.twig', [
            'editLocale' => $locale,
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_event_delete')]
    public function eventDelete(EventRepository $repo): Response
    {
        dump('delete');
        exit;
    }
}
