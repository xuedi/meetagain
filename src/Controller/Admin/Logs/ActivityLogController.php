<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Activity\ActivityService;
use App\Admin\Tabs\AdminTabsInterface;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs')]
final class ActivityLogController extends AbstractLogsController implements AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct($translator, 'activity');
    }

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_logs',
                    route: 'app_admin_activity_log',
                    active: 'logs',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }

    #[Route('', name: 'app_admin_logs')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_activity_log');
    }

    #[Route('/activity', name: 'app_admin_activity_log')]
    public function list(): Response
    {
        return $this->render('admin/logs/logs_activity_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activities' => $this->activityService->getAdminList(),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/activity/{id}', name: 'app_admin_activity_log_show')]
    public function show(int $id): Response
    {
        $activity = $this->activityService->getAdminDetail($id);
        if ($activity === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/logs/logs_activity_show.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activity' => $activity,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
