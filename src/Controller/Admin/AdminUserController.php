<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/user')]
class AdminUserController extends AbstractController
{
    #[Route('/', name: 'app_admin_user')]
    public function userList(UserRepository $repo): Response
    {
        return $this->render('admin/user/list.html.twig', [
            'needForApproval' => $repo->findBy(['status' => 1], ['createdAt' => 'desc']),
            'users' => $repo->findBy([], ['createdAt' => 'desc']),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_user_approve', methods: ['GET'])]
    public function userApprove(User $user, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $user->setStatus(UserStatus::Active);

        $email = new TemplatedEmail();
        $email->from(new Address('registration@dragon-descendants.de', 'Dragon Descendants Meetup'));
        $email->to((string) $user->getEmail());
        $email->subject('Welcome!');
        $email->htmlTemplate('registration/approved_email.html.twig');

        $mailer->send($email);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }

    #[Route('/{id}/deny', name: 'app_admin_user_deny', methods: ['GET'])]
    public function userDeny(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Denied);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['GET'])]
    public function userDelete(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Deleted);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }
}
