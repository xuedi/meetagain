<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\User;
use App\Form\AnnouncementType;
use App\Repository\AnnouncementRepository;
use App\Service\AnnouncementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminAnnouncementController extends AbstractController
{
    public function __construct(
        private readonly AnnouncementRepository $repo,
        private readonly AnnouncementService $announcementService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/system/announcements', name: 'app_admin_announcement')]
    public function list(): Response
    {
        return $this->render('admin/announcement/list.html.twig', [
            'active' => 'announcement',
            'announcements' => $this->repo->findAllOrderedByDate(),
        ]);
    }

    #[Route('/admin/system/announcements/new', name: 'app_admin_announcement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $announcement = new Announcement();
        $form = $this->createForm(AnnouncementType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            assert($user instanceof User);

            $this->announcementService->createAnnouncement(
                $announcement->getTitle(),
                $announcement->getContent(),
                $user
            );

            $this->addFlash('success', 'announcement_created');

            return $this->redirectToRoute('app_admin_announcement');
        }

        return $this->render('admin/announcement/new.html.twig', [
            'active' => 'announcement',
            'form' => $form,
        ]);
    }

    #[Route('/admin/system/announcements/{id}', name: 'app_admin_announcement_view')]
    public function view(Announcement $announcement): Response
    {
        $preview = null;
        if ($announcement->isDraft()) {
            $preview = $this->announcementService->renderPreview($announcement);
        }

        return $this->render('admin/announcement/view.html.twig', [
            'active' => 'announcement',
            'announcement' => $announcement,
            'preview' => $preview,
        ]);
    }

    #[Route('/admin/system/announcements/{id}/preview', name: 'app_admin_announcement_preview')]
    public function preview(Announcement $announcement): Response
    {
        $preview = $this->announcementService->renderPreview($announcement);

        return $this->render('admin/announcement/preview.html.twig', [
            'active' => 'announcement',
            'announcement' => $announcement,
            'preview' => $preview,
        ]);
    }

    #[Route('/admin/system/announcements/{id}/send', name: 'app_admin_announcement_send', methods: ['POST'])]
    public function send(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_already_sent');

            return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
        }

        $user = $this->getUser();
        assert($user instanceof User);

        $recipientCount = $this->announcementService->send($announcement, $user);

        $this->addFlash('success', sprintf('announcement_sent_success: %d', $recipientCount));

        return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
    }

    #[Route('/admin/system/announcements/{id}/edit', name: 'app_admin_announcement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_cannot_edit_sent');

            return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
        }

        $form = $this->createForm(AnnouncementType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'announcement_updated');

            return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
        }

        return $this->render('admin/announcement/edit.html.twig', [
            'active' => 'announcement',
            'form' => $form,
            'announcement' => $announcement,
        ]);
    }

    #[Route('/admin/system/announcements/{id}/delete', name: 'app_admin_announcement_delete', methods: ['POST'])]
    public function delete(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_cannot_delete_sent');

            return $this->redirectToRoute('app_admin_announcement');
        }

        $this->em->remove($announcement);
        $this->em->flush();

        $this->addFlash('success', 'announcement_deleted');

        return $this->redirectToRoute('app_admin_announcement');
    }
}
