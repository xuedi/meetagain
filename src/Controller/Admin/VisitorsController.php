<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\UserStatus;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VisitorsController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'Logs',
            label: 'menu_admin_visitors',
            route: 'app_admin_visitors',
            active: 'visitors',
            linkRole: 'ROLE_ORGANIZER',
        );
    }

    public function __construct(
        private readonly UserRepository $userRepo,
    ) {}

    #[Route('/admin/visitors', name: 'app_admin_visitors')]
    public function index(): Response
    {
        $users = $this->userRepo->findBy([], ['createdAt' => 'desc']);
        $needForApproval = array_filter($users, fn($u) => $u->getStatus() === UserStatus::EmailVerified);

        return $this->render('admin/logs/visitors_index.html.twig', [
            'active' => 'visitors',
            'users' => $users,
            'needForApproval' => $needForApproval,
        ]);
    }
}
