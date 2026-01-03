<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\UserStatus;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminVisitorsController extends AbstractController
{
    public function __construct(private readonly UserRepository $userRepo)
    {
    }

    #[Route('/admin/visitors/', name: 'app_admin_visitors')]
    public function index(): Response
    {
        $users = $this->userRepo->findBy([], ['createdAt' => 'desc']);
        $needForApproval = array_filter($users, fn ($u) => $u->getStatus() === UserStatus::EmailVerified);

        return $this->render('admin/visitors/index.html.twig', [
            'active' => 'visitors',
            'users' => $users,
            'needForApproval' => $needForApproval,
        ]);
    }
}
