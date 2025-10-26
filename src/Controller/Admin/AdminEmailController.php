<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminEmailController extends AbstractController
{
    #[Route('/admin/email/', name: 'app_admin_email')]
    public function hostList(EmailService $emailService): Response
    {
        return $this->render('admin/email/list.html.twig', [
            'active' => 'email',
            'emails' => $emailService->getMockEmailList(),
        ]);
    }
}
