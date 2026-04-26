<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminMemberApproved;
use App\Activity\Messages\AdminMemberDemoted;
use App\Activity\Messages\AdminMemberDenied;
use App\Activity\Messages\AdminMemberPromoted;
use App\Activity\Messages\AdminMemberRestricted;
use App\Activity\Messages\AdminMemberStatusChanged;
use App\Activity\Messages\AdminMemberUnrestricted;
use App\Activity\Messages\AdminMemberUnverified;
use App\Activity\Messages\AdminMemberVerified;
use App\Entity\AdminLink;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Admin\Member\AdminMemberListFilterService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER')]
final class MemberController extends AbstractAdminController
{
    private const array ALLOWED_ROLES = ['user', 'admin'];
    private const array ALLOWED_FLAGS = ['verified', 'restricted'];
    private const array ALLOWED_STATUS_TRANSITIONS = [
        UserStatus::EmailVerified->value => [UserStatus::Active, UserStatus::Denied],
        UserStatus::Active->value        => [UserStatus::Blocked],
        UserStatus::Blocked->value       => [UserStatus::Active],
        UserStatus::Registered->value    => [UserStatus::Denied],
    ];

    public function __construct(
        private readonly UserRepository $repo,
        private readonly AdminMemberListFilterService $filterService,
        private readonly EntityManagerInterface $em,
        private readonly EntityActionDispatcher $dispatcher,
        private readonly ActivityService $activityService,
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

    #[Route('/admin/member/{id}/set-role/{role}', name: 'app_admin_member_set_role', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setRole(User $user, string $role, Request $request): Response
    {
        if (($redirect = $this->guardCommonChecks($user)) !== null) {
            return $redirect;
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new BadRequestHttpException('Invalid role.');
        }

        if (!$this->isCsrfTokenValid('member_set_role_' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        $newRole = UserRole::from(strtoupper($role));
        $oldRole = $user->getRole();

        if ($oldRole === $newRole) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        $user->setRole($newRole);
        $this->em->flush();

        $type = $newRole === UserRole::Admin ? AdminMemberPromoted::TYPE : AdminMemberDemoted::TYPE;
        $this->activityService->log($type, $this->getAuthedUser(), ['user_id' => $user->getId()]);

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    #[Route('/admin/member/{id}/toggle/{flag}', name: 'app_admin_member_toggle_flag', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleFlag(User $user, string $flag, Request $request): Response
    {
        if (($redirect = $this->guardCommonChecks($user)) !== null) {
            return $redirect;
        }

        if (!in_array($flag, self::ALLOWED_FLAGS, true)) {
            throw new BadRequestHttpException('Invalid flag.');
        }

        if (!$this->isCsrfTokenValid('member_toggle_' . $user->getId() . '_' . $flag, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        $newValue = match ($flag) {
            'verified' => !$user->isVerified(),
            'restricted' => !$user->isRestricted(),
        };
        match ($flag) {
            'verified' => $user->setVerified($newValue),
            'restricted' => $user->setRestricted($newValue),
        };
        $this->em->flush();

        $type = match ([$flag, $newValue]) {
            ['verified', true]    => AdminMemberVerified::TYPE,
            ['verified', false]   => AdminMemberUnverified::TYPE,
            ['restricted', true]  => AdminMemberRestricted::TYPE,
            ['restricted', false] => AdminMemberUnrestricted::TYPE,
        };
        $this->activityService->log($type, $this->getAuthedUser(), ['user_id' => $user->getId()]);

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    #[Route('/admin/member/{id}/set-status/{status}', name: 'app_admin_member_set_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setStatus(User $user, string $status, Request $request): Response
    {
        if (($redirect = $this->guardCommonChecks($user)) !== null) {
            return $redirect;
        }

        if (!is_numeric($status)) {
            throw new BadRequestHttpException('Invalid status.');
        }
        $newStatus = UserStatus::tryFrom((int) $status);
        if ($newStatus === null) {
            throw new BadRequestHttpException('Invalid status.');
        }

        $oldStatus = $user->getStatus();
        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$oldStatus->value] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        if (!$this->isCsrfTokenValid('member_set_status_' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        $user->setStatus($newStatus);
        $this->em->flush();

        $type = match (true) {
            $oldStatus === UserStatus::EmailVerified && $newStatus === UserStatus::Active => AdminMemberApproved::TYPE,
            $newStatus === UserStatus::Denied => AdminMemberDenied::TYPE,
            default => AdminMemberStatusChanged::TYPE,
        };

        $meta = ['user_id' => $user->getId()];
        if ($type === AdminMemberStatusChanged::TYPE) {
            $meta['old'] = $oldStatus->value;
            $meta['new'] = $newStatus->value;
        }
        $this->activityService->log($type, $this->getAuthedUser(), $meta);

        return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
    }

    private function guardCommonChecks(User $user): ?Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        if ($user->getId() === $this->getAuthedUser()->getId()) {
            return $this->redirectToRoute('app_admin_member_edit', ['id' => $user->getId()]);
        }

        if ($user->getRole() === UserRole::System) {
            throw $this->createAccessDeniedException('Cannot modify system users.');
        }

        return null;
    }
}
