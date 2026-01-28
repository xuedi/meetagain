<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\Cms;
use App\Form\AnnouncementType;
use App\Repository\AnnouncementRepository;
use App\Service\AnnouncementService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AnnouncementController extends AbstractController
{
    public function __construct(
        private readonly AnnouncementRepository $repo,
        private readonly AnnouncementService $announcementService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function list(): Response
    {
        return $this->render('admin_modules/system/announcement_list.html.twig', [
            'active' => 'announcement',
            'announcements' => $this->repo->findAllOrderedByDate(),
        ]);
    }

    public function new(Request $request): Response
    {
        $announcement = new Announcement();
        $form = $this->createForm(AnnouncementType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $announcement->setCreatedBy($this->getUser());
            $announcement->setCreatedAt(new DateTimeImmutable());
            $announcement->setStatus(AnnouncementStatus::Draft);

            $this->em->persist($announcement);
            $this->em->flush();

            return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
        }

        return $this->render('admin_modules/system/announcement_new.html.twig', [
            'active' => 'announcement',
            'form' => $form,
        ]);
    }

    public function createFromCms(Cms $cmsPage): Response
    {
        $announcement = new Announcement();
        $announcement->setCmsPage($cmsPage);
        $announcement->setCreatedBy($this->getUser());
        $announcement->setCreatedAt(new DateTimeImmutable());
        $announcement->setStatus(AnnouncementStatus::Draft);

        $this->em->persist($announcement);
        $this->em->flush();

        return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
    }

    public function view(Announcement $announcement, Request $request): Response
    {
        $locale = $request->query->get('locale', 'en');
        $preview = null;
        if ($announcement->isDraft()) {
            $preview = $this->announcementService->renderPreview($announcement, $locale);
        }

        return $this->render('admin_modules/system/announcement_view.html.twig', [
            'active' => 'announcement',
            'announcement' => $announcement,
            'preview' => $preview,
            'previewLocale' => $locale,
        ]);
    }

    public function send(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_already_sent');

            return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
        }

        $recipientCount = $this->announcementService->send($announcement);

        $this->addFlash('success', sprintf('announcement_sent_success: %d', $recipientCount));

        return $this->redirectToRoute('app_admin_announcement_view', ['id' => $announcement->getId()]);
    }

    public function delete(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_cannot_delete_sent');

            return $this->redirectToRoute('app_admin_announcement');
        }

        $this->em->remove($announcement);
        $this->em->flush();

        return $this->redirectToRoute('app_admin_announcement');
    }
}
