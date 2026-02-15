<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Repository\EmailQueueRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/sendlog')]
class SendlogController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly EmailQueueRepository $emailQueueRepo,
    ) {}

    #[Route('', name: 'app_admin_email_sendlog')]
    public function sendlog(): Response
    {
        $emails = $this->emailQueueRepo
            ->createQueryBuilder('eq')
            ->orderBy('eq.createdAt', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

        return $this->render('admin/email/sendlog/list.html.twig', [
            'active' => 'email',
            'emails' => $emails,
        ]);
    }
}
