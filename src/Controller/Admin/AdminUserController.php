<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmailType\Welcome;
use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\UserType;
use App\Message\PrepareEmail;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminUserController extends AbstractController
{
    #[Route('/admin/user/', name: 'app_admin_user')]
    public function userList(UserRepository $repo): Response
    {
        return $this->render('admin/user/list.html.twig', [
            'active' => 'user',
            'users' => $repo->findBy([], ['createdAt' => 'desc']),
        ]);
    }

    #[Route('/admin/user/{id}', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('admin/user/edit.html.twig', [
            'active' => 'user',
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/admin/user/{id}/approve', name: 'app_admin_user_approve', methods: ['GET'])]
    public function userApprove(User $user, EntityManagerInterface $em, MessageBusInterface $messageBus): Response
    {
        $user->setStatus(UserStatus::Active);

        $messageBus->dispatch(PrepareEmail::byType(Welcome::create($user)));

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/user/{id}/deny', name: 'app_admin_user_deny', methods: ['GET'])]
    public function userDeny(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Denied);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/user/{id}/delete', name: 'app_admin_user_delete', methods: ['GET'])]
    public function userDelete(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Deleted);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }
}
