<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\ImageReport;
use App\Entity\SupportRequest;
use App\Enum\ImageReportStatus;
use App\Enum\SupportRequestStatus;
use App\Repository\ImageReportRepository;
use App\Repository\SupportRequestRepository;
use App\Service\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support')]
final class SupportController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'System', links: [
            new AdminLink(label: 'Support', route: 'app_admin_support_list', active: 'support'),
        ]);
    }

    public function __construct(
        private readonly SupportRequestRepository $supportRequestRepo,
        private readonly ImageReportRepository $imageReportRepo,
        private readonly ImageLocationService $imageLocationService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_support_list')]
    public function list(): Response
    {
        $requests = $this->supportRequestRepo
            ->createQueryBuilder('sr')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/support/list.html.twig', [
            'active' => 'support',
            'requests' => $requests,
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_admin_support_mark_read', methods: ['POST'])]
    public function markRead(int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if ($request instanceof SupportRequest) {
            $request->setStatus(SupportRequestStatus::Read);
            $this->em->persist($request);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_support_list');
    }

    #[Route('/reports', name: 'app_admin_support_reports')]
    public function reports(): Response
    {
        $reports = $this->imageReportRepo
            ->createQueryBuilder('ir')
            ->orderBy('ir.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/support/reports.html.twig', [
            'active' => 'support',
            'reports' => $reports,
        ]);
    }

    #[Route('/reports/{id}', name: 'app_admin_support_report_show')]
    public function showReport(int $id): Response
    {
        $report = $this->imageReportRepo->find($id);
        $location = $report?->getImage() !== null
            ? $this->imageLocationService->locate($report->getImage())
            : null;

        return $this->render('admin/support/report_show.html.twig', [
            'active' => 'support',
            'report' => $report,
            'location' => $location,
        ]);
    }

    #[Route('/reports/resolve/{id}', name: 'app_admin_support_report_resolve', methods: ['POST'])]
    public function resolveReport(int $id): Response
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
