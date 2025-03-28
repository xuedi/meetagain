<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\UserType;
use App\Repository\NotFoundLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/visitors')]
class AdminVisitorsController extends AbstractController
{
    #[Route('/', name: 'app_admin_visitors')]
    public function notFoundVisits(NotFoundLogRepository $repo): Response
    {
        return $this->render('admin/visitors/index.html.twig', [
            'notFound' => $repo->findAll(),
        ]);
    }
}
