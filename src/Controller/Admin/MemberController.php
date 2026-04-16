<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminMemberApproved;
use App\Activity\Messages\AdminMemberDenied;
use App\Entity\AdminLink;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Admin\Member\AdminMemberListFilterService;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER')]
final class MemberController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EmailService $emailService,
        private readonly AdminMemberListFilterService $filterService,
        private readonly EntityManagerInterface $em,
        private readonly ActivityService $activityService,
        private readonly EntityActionDispatcher $dispatcher,
        private readonly Security $security,
    ) {}

    #[Override]
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'Content',
            links: [
                new AdminLink(label: 'Members', route: 'app_admin_member', active: 'member', role: 'ROLE_ORGANIZER'),
            ],
            sectionPriority: 50,
        );
    }

    #[Route('/admin/member', name: 'app_admin_member')]
    public function list(): Response
    {
        // Apply member filtering (multisite WhitelabelMemberFilter will inject restrictions)
        $filterResult = $this->filterService->getUserIdFilter();
        $users = $this->repo->findAllForAdmin($filterResult->getUserIds());

        // Get pending users (EmailVerified status) for approval section
        $pendingUsers = [];
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $pendingUsers = $this->repo->findByStatus(UserStatus::EmailVerified);
        }

        return $this->render('admin/member/list.html.twig', [
            'active' => 'member',
            'users' => $users,
            'pendingUsers' => $pendingUsers,
        ]);
    }

    #[Route('/admin/member/edit/{id}', name: 'app_admin_member_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        // Validate member is accessible in current context
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        // Route to appropriate view based on role
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->editAdmin($request, $user);
        }

        return $this->editOrganizer($user);
    }

    private function editAdmin(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle unmapped boolean fields
            $user->setVerified((bool) $form->get('verified')->getData());
            $user->setRestricted((bool) $form->get('restricted')->getData());
            $user->setOsmConsent((bool) $form->get('osmConsent')->getData());
            $user->setPublic((bool) $form->get('public')->getData());
            $user->setTagging((bool) $form->get('tagging')->getData());

            $this->em->flush();

            return $this->redirectToRoute('app_admin_member');
        }

        return $this->render('admin/member/edit.html.twig', [
            'active' => 'member',
            'user' => $user,
            'form' => $form,
        ]);
    }

    private function editOrganizer(User $user): Response
    {
        return $this->render('admin/member/edit_organizer.html.twig', [
            'active' => 'member',
            'user' => $user,
        ]);
    }

    #[Route('/admin/member/approve/{id}', name: 'app_admin_member_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('approve_user', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($user->getStatus() !== UserStatus::EmailVerified) {
            throw $this->createNotFoundException('Only pending users can be approved.');
        }

        $user->setStatus(UserStatus::Active);

        $this->emailService->prepareWelcome($user);
        $this->emailService->sendQueue();

        $this->em->persist($user);
        $this->em->flush();

        $admin = $this->getAuthedUser();
        $this->activityService->log(AdminMemberApproved::TYPE, $admin, ['user_id' => $user->getId()]);

        return $this->redirectToRoute('app_admin_member');
    }

    #[Route('/admin/member/deny/{id}', name: 'app_admin_member_deny', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deny(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('deny_user', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($user->getStatus() !== UserStatus::EmailVerified) {
            throw $this->createNotFoundException('Only pending users can be denied.');
        }

        $user->setStatus(UserStatus::Denied);

        $this->em->persist($user);
        $this->em->flush();

        $admin = $this->getAuthedUser();
        $this->activityService->log(AdminMemberDenied::TYPE, $admin, ['user_id' => $user->getId()]);

        return $this->redirectToRoute('app_admin_member');
    }

    #[Route('/admin/member/delete/{id}', name: 'app_admin_member_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user): Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        $userId = $user->getId();

        $user->setStatus(UserStatus::Deleted);

        $this->em->persist($user);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::DeleteUser, $userId);

        return $this->redirectToRoute('app_admin_member');
    }

    #[Route('/admin/member/{id}/promote-organizer', name: 'app_admin_member_promote_organizer', methods: ['POST'])]
    public function promoteToOrganizer(User $user): Response
    {
        // Validate member is accessible
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        // Prevent self-promotion
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot promote yourself.');

            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        // Prevent promoting system users
        if ($user->getRole() === UserRole::System) {
            throw $this->createAccessDeniedException('Cannot modify system users.');
        }

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    #[Route('/admin/member/{id}/toggle-verified', name: 'app_admin_member_toggle_verified', methods: ['POST'])]
    public function toggleVerified(User $user): Response
    {
        // Validate member is accessible
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        // Prevent modifying system users
        if ($user->getRole() === UserRole::System) {
            throw $this->createAccessDeniedException('Cannot modify system users.');
        }

        $user->setVerified(!$user->isVerified());
        $this->em->flush();

        $this->addFlash('success', $user->isVerified() ? 'Member verified.' : 'Member unverified.');

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }
}
