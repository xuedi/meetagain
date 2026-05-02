<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Entity\Announcement;
use App\Entity\Cms;
use App\EntityActionDispatcher;
use App\Enum\AnnouncementStatus;
use App\Enum\EntityAction;
use App\Form\AnnouncementType;
use App\Repository\AnnouncementRepository;
use App\Service\Cms\AnnouncementService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/announcements')]
final class AnnouncementsController extends AbstractEmailController implements AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly AnnouncementRepository $announcementRepo,
        private readonly AnnouncementService $announcementService,
        private readonly EntityManagerInterface $em,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly LanguageService $languageService,
    ) {
        parent::__construct($translator, 'announcements');
    }

    #[Route('', name: 'app_admin_email_announcements')]
    public function announcements(): Response
    {
        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_cms.announcements_list_intro'))],
        );

        return $this->render('admin/email/announcements/list.html.twig', [
            'active' => 'email',
            'announcements' => $this->announcementRepo->findAllOrderedByDate(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
        $preview = $this->announcementService->renderPreview($announcement, $locale);

        $adminTop = new AdminTop(
            info: $this->buildViewInfo($announcement),
            actions: $this->buildViewActions($announcement),
        );

        return $this->render('admin/email/announcements/view.html.twig', [
            'active' => 'email',
            'announcement' => $announcement,
            'preview' => $preview,
            'previewLocale' => $locale,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/send', name: 'app_admin_email_announcements_send')]
    public function announcementsSend(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'admin_cms.flash_error_already_sent');

            return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
        }

        $recipientCount = $this->announcementService->send($announcement);

        $this->addFlash('success', $this->translator->trans('admin_cms.flash_success_sent', ['%count%' => $recipientCount]));

        return $this->redirectToRoute('app_admin_email_announcements_view', ['id' => $announcement->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_announcements_delete')]
    public function announcementsDelete(Announcement $announcement): Response
    {
        if (!$announcement->isDraft()) {
            $this->addFlash('error', 'admin_cms.flash_error_cannot_delete_sent');

            return $this->redirectToRoute('app_admin_email_announcements');
        }

        $announcementId = $announcement->getId();
        $this->em->remove($announcement);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteAnnouncement, $announcementId);

        return $this->redirectToRoute('app_admin_email_announcements');
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildViewInfo(Announcement $announcement): array
    {
        $title = $announcement->getCmsPage() !== null
            ? (string) $announcement->getCmsPage()->getSlug()
            : 'Announcement #' . $announcement->getId();

        $statusVariant = $announcement->isDraft() ? 'is-warning' : 'is-success';
        $statusKey = $announcement->isDraft() ? 'admin_cms.draft' : 'admin_cms.sent';

        return [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>',
                htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $statusVariant,
                htmlspecialchars($this->translator->trans($statusKey), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];
    }

    /**
     * @return list<AdminTopActionButton>
     */
    private function buildViewActions(Announcement $announcement): array
    {
        $actions = [];

        if ($announcement->isDraft()) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('global.button_delete'),
                target: $this->generateUrl('app_admin_email_announcements_delete', ['id' => $announcement->getId()]),
                icon: 'trash',
                variant: 'is-danger',
            );
        }
        if ($announcement->getCmsPage() !== null) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_cms.button_edit_cms_page'),
                target: $this->generateUrl('app_admin_cms_edit', ['id' => $announcement->getCmsPage()->getId()]),
                icon: 'edit',
            );
        }
        if ($announcement->isDraft()) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_cms.button_send_announcement'),
                target: $this->generateUrl('app_admin_email_announcements_send', ['id' => $announcement->getId()]),
                icon: 'paper-plane',
                confirm: $this->translator->trans('admin_cms.confirm_send_announcement'),
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_email_announcements'),
            icon: 'arrow-left',
        );

        return $actions;
    }
}
