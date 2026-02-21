<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\Cms;
use App\Enum\EntityAction;
use App\Form\AnnouncementType;
use App\Repository\AnnouncementRepository;
use App\Service\AnnouncementService;
use App\Service\EntityActionDispatcher;
use App\Service\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/announcements')]
class AnnouncementsController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly AnnouncementRepository $announcementRepo,
        private readonly AnnouncementService $announcementService,
        private readonly EntityManagerInterface $em,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly LanguageService $languageService,
    ) {}

    #[Route('', name: 'app_admin_email_announcements')]
    public function announcements(): Response
    {
        return $this->render('admin/email/announcements/list.html.twig', [
            'active' => 'email',
            'announcements' => $this->announcementRepo->findAllOrderedByDate(),
        ]);
    }

    #[Route('/new', name: 'app_admin_email_announcements_new', methods: ['GET', 'POST'])]
    public function announcementsNew(Request $request): Response
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

            $this->entityActionDispatcher->dispatch(EntityAction::CreateAnnouncement, $announcement->getId());

            return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
        }

        return $this->render('admin/email/announcements/new.html.twig', [
            'active' => 'email',
            'form' => $form,
        ]);
    }

    #[Route('/from-cms/{id}', name: 'app_admin_email_announcements_from_cms')]
    public function announcementsFromCms(Cms $cmsPage): Response
    {
        $announcement = new Announcement();
        $announcement->setCmsPage($cmsPage);
        $announcement->setCreatedBy($this->getUser());
        $announcement->setCreatedAt(new DateTimeImmutable());
        $announcement->setStatus(AnnouncementStatus::Draft);

        $this->em->persist($announcement);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::CreateAnnouncement, $announcement->getId());

        return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
    }

    #[Route('/{id}', name: 'app_admin_email_announcements_view')]
    public function announcementsView(Announcement $announcement, Request $request): Response
    {
        $locale = $request->query->get('locale', $this->languageService->getAdminFilteredEnabledCodes()[0]);
        $preview = null;
        if ($announcement->isDraft()) {
            $preview = $this->announcementService->renderPreview($announcement, $locale);
        }

        return $this->render('admin/email/announcements/view.html.twig', [
            'active' => 'email',
            'announcement' => $announcement,
            'preview' => $preview,
            'previewLocale' => $locale,
        ]);
    }

    #[Route('/{id}/send', name: 'app_admin_email_announcements_send', methods: ['POST'])]
    public function announcementsSend(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_already_sent');

            return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
        }

        $recipientCount = $this->announcementService->send($announcement);

        $this->addFlash('success', sprintf('announcement_sent_success: %d', $recipientCount));

        return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_announcements_delete', methods: ['POST'])]
    public function announcementsDelete(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'announcement_cannot_delete_sent');

            return $this->redirectToRoute('app_admin_email_announcements');
        }

        $announcementId = $announcement->getId();
        $this->em->remove($announcement);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteAnnouncement, $announcementId);

        return $this->redirectToRoute('app_admin_email_announcements');
    }
}
