<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class MemberController extends AbstractController
{
    public const string ROUTE_MEMBER = 'app_member';
    private const int PAGE_SIZE = 24;

    #[Route('/members/{page}', name: self::ROUTE_MEMBER)]
    public function index(UserRepository $repo, int $page = 1): Response
    {
        $response = $this->getResponse();
        $offset = ($page - 1) * self::PAGE_SIZE;
        if ($this->getUser() instanceof User) {
            $userTotal = $repo->getNumberOfActiveMembers();
            $users = $repo->findActiveMembers(self::PAGE_SIZE, $offset);
        } else {
            $userTotal = $repo->getNumberOfActivePublicMembers();
            $users = $repo->findActivePublicMembers(self::PAGE_SIZE, $offset);
        }
        return $this->render(
            'member/index.html.twig',
            [
                'users' => $users,
                'userTotal' => $userTotal,
                'pageSize' => self::PAGE_SIZE,
                'pageCurrent' => $page,
                'pageTotal' => ceil($userTotal / self::PAGE_SIZE),
            ],
            $response,
        );
    }

    #[Route('/members/view/{id}', name: 'app_member_view')]
    public function view(UserRepository $repo, int $id): Response
    {
        $response = $this->getResponse();
        try {
            $currentUser = $this->getAuthedUser();
            $userDetails = $repo->findOneBy(['id' => $id]);

            return $this->render(
                'member/view.html.twig',
                [
                    'currentUser' => $currentUser,
                    'userDetails' => $userDetails,
                    'isFollow' => $currentUser->getFollowing()->contains($userDetails),
                ],
                $response,
            );
        } catch (AuthenticationCredentialsNotFoundException) {
            return $this->render('member/403.html.twig');
        }
    }

    #[Route('/members/toggleFollow/{id}', name: 'app_member_toggle_follow')]
    public function toggleFollow(FriendshipService $service, int $id): Response
    {
        return $service->toggleFollow($id, 'app_member_view');
    }

    #[Route('/members/rotate-avatar/{id}', name: 'app_member_rotate_avatar')]
    public function rotateProfileImage(UserRepository $repo, ImageService $imageService, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $user = $repo->findOneBy(['id' => $id]);
        if ($user->getImage() !== null) {
            $imageService->rotateThumbNail($user->getImage());
        }

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/remove-image/{id}', name: 'app_member_remove_avatar')]
    public function removeProfileImage(UserRepository $repo, EntityManagerInterface $em, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $user = $repo->findOneBy(['id' => $id]);
        $user->setImage(null);
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/restrict/{id}', name: 'app_member_restrict')]
    public function restrictUser(UserRepository $repo, EntityManagerInterface $em, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $user = $repo->findOneBy(['id' => $id]);
        $user->setRestricted(!$user->isRestricted());
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/verify/{id}', name: 'app_member_verify')]
    public function verifyUser(UserRepository $repo, EntityManagerInterface $em, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $user = $repo->findOneBy(['id' => $id]);
        $user->setVerified(!$user->isVerified());
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/takeover/{id}', name: 'app_member_takeover')]
    public function takeoverUser(UserRepository $repo, Security $security, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $repo->findOneBy(['id' => $id]);
        $security->login($user);

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }
}
