<?php declare(strict_types=1);

namespace Plugin\AdminTables\Controller;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\AdminTables\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EmailService $emailService,
    ) {}

    #[Route('/admin/user', name: 'app_admin_user')]
    public function userList(): Response
    {
        return $this->render('@AdminTables/tables/user_list.html.twig', [
            'active' => 'user',
            'users' => $this->repo->findBy([], ['createdAt' => 'desc']),
        ]);
    }

    #[Route('/admin/user/edit/{id}', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('@AdminTables/tables/user_edit.html.twig', [
            'active' => 'user',
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/admin/user/approve/{id}', name: 'app_admin_user_approve', methods: ['POST'])]
    public function userApprove(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Active);

        $this->emailService->prepareWelcome($user);
        $this->emailService->sendQueue(); // TODO: use cron instead

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/user/deny/{id}', name: 'app_admin_user_deny', methods: ['POST'])]
    public function userDeny(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Denied);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/user/delete/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function userDelete(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatus(UserStatus::Deleted);

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_user');
    }
}
