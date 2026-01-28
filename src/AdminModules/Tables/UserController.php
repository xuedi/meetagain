<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EmailService $emailService,
    ) {}

    public function userList(): Response
    {
        return $this->render('admin_modules/tables/user_list.html.twig', [
            'active' => 'user',
            'users' => $this->repo->findBy([], ['createdAt' => 'desc']),
        ]);
    }

    public function userEdit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('admin_modules/tables/user_edit.html.twig', [
            'active' => 'user',
            'user' => $user,
            'form' => $form,
        ]);
    }

    public function userApprove(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Active);

        $this->emailService->prepareWelcome($user);
        $this->emailService->sendQueue(); // TODO: use cron instead

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    public function userDeny(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Denied);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    public function userDelete(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Deleted);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }
}
