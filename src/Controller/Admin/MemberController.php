<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Admin\Member\AdminMemberListFilterService;
use App\Repository\UserRepository;
use App\Service\Member\MemberActionException;
use App\Service\Member\MemberActionFailure;
use App\Service\Member\UserService;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER')]
final class MemberController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly AdminMemberListFilterService $filterService,
        private readonly UserService $userService,
    ) {}

    #[Override]
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(label: 'admin_shell.menu_member', route: 'app_admin_member', active: 'member', role: 'ROLE_ORGANIZER'),
            ],
            sectionPriority: 50,
        );
    }

    #[Route('/admin/member', name: 'app_admin_member')]
    public function list(): Response
    {
        $filterResult = $this->filterService->getUserIdFilter();
        $users = $this->repo->findAllForAdmin($filterResult->getUserIds());

        return $this->render('admin/member/list.html.twig', [
            'active' => 'member',
            'users' => $users,
        ]);
    }

    #[Route('/admin/member/edit/{id}', name: 'app_admin_member_edit', methods: ['GET'])]
    public function edit(User $user): Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        return $this->render('admin/member/edit.html.twig', [
            'active' => 'member',
            'user' => $user,
        ]);
    }

    #[Route('/admin/member/delete/{id}', name: 'app_admin_member_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user): Response
    {
        $this->assertAccessible($user);

        try {
            $this->userService->softDelete($this->getAuthedUser(), $user);
        } catch (MemberActionException $e) {
            $this->handleFailure($e);
        }

        return $this->redirectToRoute('app_admin_member');
    }

    #[Route('/admin/member/{id}/set-role/{role}', name: 'app_admin_member_set_role', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setRole(User $user, string $role, Request $request): Response
    {
        $this->assertAccessible($user);

        if (!in_array($role, UserService::ALLOWED_ROLES, true)) {
            throw new BadRequestHttpException('Invalid role.');
        }

        if (!$this->isCsrfTokenValid('member_set_role_' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        $newRole = UserRole::from(strtoupper($role));

        try {
            $this->userService->changeRole($this->getAuthedUser(), $user, $newRole);
        } catch (MemberActionException $e) {
            $this->handleFailure($e);
        }

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    #[Route('/admin/member/{id}/toggle/{flag}', name: 'app_admin_member_toggle_flag', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleFlag(User $user, string $flag, Request $request): Response
    {
        $this->assertAccessible($user);

        if (!in_array($flag, UserService::ALLOWED_FLAGS, true)) {
            throw new BadRequestHttpException('Invalid flag.');
        }

        if (!$this->isCsrfTokenValid('member_toggle_' . $user->getId() . '_' . $flag, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        try {
            match ($flag) {
                'verified' => $this->userService->toggleVerified($this->getAuthedUser(), $user),
                'restricted' => $this->userService->toggleRestricted($this->getAuthedUser(), $user),
            };
        } catch (MemberActionException $e) {
            $this->handleFailure($e);
        }

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    #[Route('/admin/member/{id}/set-status/{status}', name: 'app_admin_member_set_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setStatus(User $user, string $status, Request $request): Response
    {
        $this->assertAccessible($user);

        if (!is_numeric($status)) {
            throw new BadRequestHttpException('Invalid status.');
        }
        $newStatus = UserStatus::tryFrom((int) $status);
        if ($newStatus === null) {
            throw new BadRequestHttpException('Invalid status.');
        }

        if (!$this->isCsrfTokenValid('member_set_status_' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        try {
            $this->userService->transitionStatus($this->getAuthedUser(), $user, $newStatus);
        } catch (MemberActionException $e) {
            $this->handleFailure($e);
        }

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    private function assertAccessible(User $user): void
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }
    }

    private function handleFailure(MemberActionException $e): void
    {
        match ($e->failure) {
            MemberActionFailure::SystemUser => throw $this->createAccessDeniedException('Cannot modify system users.'),
            default => null,
        };
    }
}
