<?php declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\ModerationReport;
use App\Entity\User;
use App\Moderation\ReportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support/moderation')]
final class ModerationController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DECIDE_TOKEN = 'app_admin_support_moderation_decide';
    private const array SUSPENSION_DAYS = [1, 7, 30];

    public function __construct(
        TranslatorInterface $translator,
        private readonly ReportService $reportService,
    ) {
        parent::__construct($translator, 'moderation');
    }

    #[Route('', name: 'app_admin_support_moderation', methods: ['GET'])]
    public function list(): Response
    {
        $reports = $this->reportService->findAll();
        $openCount = count(array_filter($reports, static fn(ModerationReport $report): bool => $report->isOpen()));

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($reports), $this->translator->trans('admin_support_moderation.summary_total'))),
        ];
        $info[] = $openCount > 0
            ? new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d&nbsp;%s</span>',
                $openCount,
                $this->translator->trans('admin_support_moderation.summary_open'),
            ))
            : new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_support_moderation.summary_all_resolved'),
            ));

        return $this->render('admin/support/moderation/list.html.twig', [
            'active' => 'support',
            'reports' => $reports,
            'adminTop' => new AdminTop(info: $info),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_support_moderation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $report = $this->findReport($id);
        $admin = $this->getAdmin();
        $reportId = (int) $report->getId();

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>&nbsp;<code>%d</code>',
                htmlspecialchars($this->translator->trans('admin_support_moderation.label_id'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $reportId,
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $report->getStatus()->tagVariant(),
                htmlspecialchars($this->translator->trans($report->getStatus()->label()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];

        $actions = [];
        $subjectPath = $this->reportService->getAdminPath($report);
        if ($subjectPath !== null) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_support_moderation.button_view_subject'),
                target: $subjectPath,
                icon: 'eye',
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_support_moderation'),
            icon: 'arrow-left',
        );

        $author = $report->getAuthor();

        return $this->render('admin/support/moderation/show.html.twig', [
            'active' => 'support',
            'report' => $report,
            'history' => $author instanceof User ? $this->reportService->authorHistory($author) : null,
            'removeLabelKey' => $this->reportService->getRemoveLabelKey($report),
            'canBlock' => $this->reportService->canBlockAuthor($report, $admin),
            'canWarn' => $this->reportService->canWarnAuthor($report, $admin),
            'suspensionDays' => self::SUSPENSION_DAYS,
            'decideTokenId' => self::DECIDE_TOKEN . $reportId,
            'adminTop' => new AdminTop(info: $info, actions: $actions),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/dismiss', name: 'app_admin_support_moderation_dismiss', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dismiss(Request $request, int $id): Response
    {
        $report = $this->findValidatedReport($request, $id);
        $this->reportService->dismiss($report, $this->getAdmin(), $this->note($request));

        return $this->redirectToRoute('app_admin_support_moderation_show', ['id' => $id]);
    }

    #[Route('/{id}/remove', name: 'app_admin_support_moderation_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $id): Response
    {
        $report = $this->findValidatedReport($request, $id);
        $this->reportService->removeSubject($report, $this->getAdmin(), $this->note($request));

        return $this->redirectToRoute('app_admin_support_moderation_show', ['id' => $id]);
    }

    #[Route('/{id}/block', name: 'app_admin_support_moderation_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(Request $request, int $id): Response
    {
        $report = $this->findValidatedReport($request, $id);
        $this->reportService->blockAuthor($report, $this->getAdmin(), $this->note($request));

        return $this->redirectToRoute('app_admin_support_moderation_show', ['id' => $id]);
    }

    #[Route('/{id}/suspend', name: 'app_admin_support_moderation_suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function suspend(Request $request, int $id): Response
    {
        $report = $this->findValidatedReport($request, $id);
        $days = $request->request->getInt('days');
        if (!in_array($days, self::SUSPENSION_DAYS, true)) {
            throw new BadRequestHttpException('Invalid suspension length.');
        }

        $this->reportService->suspendAuthor($report, $this->getAdmin(), $days, $this->note($request));

        return $this->redirectToRoute('app_admin_support_moderation_show', ['id' => $id]);
    }

    #[Route('/{id}/warn', name: 'app_admin_support_moderation_warn', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function warn(Request $request, int $id): Response
    {
        $report = $this->findValidatedReport($request, $id);
        if (!$this->reportService->warnAuthor($report, $this->getAdmin(), (string) $this->note($request))) {
            $this->addFlash('error', 'admin_support_moderation.flash_warn_needs_note');
        }

        return $this->redirectToRoute('app_admin_support_moderation_show', ['id' => $id]);
    }

    private function note(Request $request): ?string
    {
        $note = trim((string) $request->request->get('note'));

        return $note === '' ? null : $note;
    }

    private function findValidatedReport(Request $request, int $id): ModerationReport
    {
        if (!$this->isCsrfTokenValid(self::DECIDE_TOKEN . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        return $this->findReport($id);
    }

    private function findReport(int $id): ModerationReport
    {
        $report = $this->reportService->find($id);
        if ($report === null) {
            throw $this->createNotFoundException();
        }

        return $report;
    }

    private function getAdmin(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
