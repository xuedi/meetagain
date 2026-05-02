<?php

declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\ImageReport;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use App\Service\Media\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support/reports')]
final class ReportsController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ImageReportRepository $imageReportRepo,
        private readonly ImageLocationService $imageLocationService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator, 'reports');
    }

    #[Route('', name: 'app_admin_support_reports')]
    public function list(): Response
    {
        $reports = $this->imageReportRepo
            ->createQueryBuilder('ir')
            ->orderBy('ir.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $openCount = 0;
        foreach ($reports as $report) {
            if (!$report->isOpen()) {
                continue;
            }

            $openCount++;
        }
        $totalCount = count($reports);

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_support.summary_total_reports'),
            )),
        ];
        $info[] = $openCount > 0
            ? new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d&nbsp;%s</span>',
                $openCount,
                $this->translator->trans('admin_support.summary_open_reports'),
            ))
            : new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_support.summary_all_resolved'),
            ));

        $adminTop = new AdminTop(info: $info);

        return $this->render('admin/support/reports.html.twig', [
            'active' => 'support',
            'reports' => $reports,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_support_report_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $report = $this->imageReportRepo->find($id);
        if ($report === null) {
            throw $this->createNotFoundException();
        }

        $location = $report->getImage() !== null ? $this->imageLocationService->locate($report->getImage()) : null;

        $statusVariant = $report->isOpen() ? 'is-warning' : 'is-success';
        $statusKey = $report->isOpen() ? 'admin_support.status_open' : 'admin_support.status_resolved';

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>&nbsp;<code>%d</code>',
                htmlspecialchars($this->translator->trans('admin_support.label_id'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $report->getId(),
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $statusVariant,
                htmlspecialchars($this->translator->trans($statusKey), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];

        $actions = [];
        if ($report->isOpen()) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_support.button_mark_resolved'),
                target: $this->generateUrl('app_admin_support_report_resolve', ['id' => $report->getId()]),
                icon: 'check',
                variant: 'is-warning',
            );
        }
        if ($report->getImage() !== null) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_support.button_image_details'),
                target: $this->generateUrl('app_admin_system_images_show', ['id' => $report->getImage()->getId()]),
                icon: 'eye',
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_support.button_back'),
            target: $this->generateUrl('app_admin_support_reports'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: $info, actions: $actions);

        return $this->render('admin/support/report_show.html.twig', [
            'active' => 'support',
            'report' => $report,
            'location' => $location,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/resolve/{id}', name: 'app_admin_support_report_resolve', requirements: ['id' => '\d+'])]
    public function resolve(int $id): Response
    {
        $report = $this->imageReportRepo->find($id);
        if ($report instanceof ImageReport) {
            $report->setStatus(ImageReportStatus::Resolved);
            $this->em->persist($report);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_support_reports');
    }
}
