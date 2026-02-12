<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Filter\Member\MemberFilterService;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MemberController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EmailService $emailService,
        private readonly MemberFilterService $filterService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return AdminNavigationConfig::single(
            section: 'System',
            label: 'Members',
            route: 'app_admin_member',
            active: 'member',
        );
    }

    #[Route('/admin/member', name: 'app_admin_member')]
    public function list(): Response
    {
        // Apply member filtering (multisite WhitelabelMemberFilter will inject restrictions)
        $filterResult = $this->filterService->getUserIdFilter();
        $users = $this->repo->findAllForAdmin($filterResult->getUserIds());

        return $this->render('admin/member/list.html.twig', [
            'active' => 'member',
            'users' => $users,
        ]);
    }

    #[Route('/admin/member/edit/{id}', name: 'app_admin_member_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        // Validate member is accessible in current context
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

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

    #[Route('/admin/member/approve/{id}', name: 'app_admin_member_approve', methods: ['POST'])]
    public function approve(User $user): Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        $user->setStatus(UserStatus::Active);

        $this->emailService->prepareWelcome($user);
        $this->emailService->sendQueue();

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/member/deny/{id}', name: 'app_admin_member_deny', methods: ['POST'])]
    public function deny(User $user): Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        $user->setStatus(UserStatus::Denied);

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/member/delete/{id}', name: 'app_admin_member_delete', methods: ['POST'])]
    public function delete(User $user): Response
    {
        if (!$this->filterService->isMemberAccessible($user->getId())) {
            throw $this->createNotFoundException('Member not found in current context.');
        }

        $user->setStatus(UserStatus::Deleted);

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_admin_member');
    }
}
