<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ImageType;
use App\Filter\Member\MemberFilterService;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Member\BlockingService;
use App\Service\Member\FriendshipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
final class MemberController extends AbstractController
{
    public const string ROUTE_MEMBER = 'app_member';
    private const int PAGE_SIZE = 24;

    public function __construct(
        private readonly UserRepository $repo,
        private readonly FriendshipService $service,
        private readonly ImageService $imageService,
        private readonly Security $security,
        private readonly BlockingService $blockingService,
        private readonly MemberFilterService $memberFilterService,
        private readonly ImageLocationService $imageLocationService,
    ) {}

    #[Route('/members/{page}', name: self::ROUTE_MEMBER)]
    public function index(int $page = 1): Response
    {
        $response = $this->getResponse();
        $offset = ($page - 1) * self::PAGE_SIZE;
        $currentUser = $this->getUser();

        // Get member filter from all registered filters
        $filterResult = $this->memberFilterService->getUserIdFilter();
        $restrictToUserIds = $filterResult->getUserIds();

        if ($currentUser instanceof User) {
            $excludeIds = $this->blockingService->getExcludedUserIds($currentUser);
            $userTotal = $this->repo->getNumberOfActiveMembers($excludeIds, $restrictToUserIds);
            $users = $this->repo->findActiveMembers(self::PAGE_SIZE, $offset, $excludeIds, $restrictToUserIds);
        } else {
            $userTotal = $this->repo->getNumberOfActivePublicMembers($restrictToUserIds);
            $users = $this->repo->findActivePublicMembers(self::PAGE_SIZE, $offset, $restrictToUserIds);
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
    public function view(int $id, Request $request): Response
    {
        if (!$this->getUser() instanceof UserInterface) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
            return $this->redirectToRoute('app_login');
        }

        $response = $this->getResponse();
        try {
            $currentUser = $this->getAuthedUser();
            $userDetails = $this->repo->findOneBy(['id' => $id]);

            if ($userDetails === null) {
                throw $this->createNotFoundException();
            }

            // If the target user has blocked the current user, deny access
            if ($this->blockingService->hasBlocked($userDetails, $currentUser)) {
                return $this->render('member/403.html.twig', [], $response);
            }

            // Check if current user has blocked the target (to show unblock button)
            $hasBlockedTarget = $this->blockingService->hasBlocked($currentUser, $userDetails);

            return $this->render(
                'member/view.html.twig',
                [
                    'currentUser' => $currentUser,
                    'userDetails' => $userDetails,
                    'isFollow' => $currentUser->getFollowing()->contains($userDetails),
                    'isBlocked' => $hasBlockedTarget,
                ],
                $response,
            );
        } catch (AuthenticationCredentialsNotFoundException) {
            return $this->render('member/403.html.twig');
        }
    }

    #[Route('/members/toggleFollow/{id}', name: 'app_member_toggle_follow')]
    #[IsGranted('ROLE_USER')]
    public function toggleFollow(int $id): Response
    {
        return $this->service->toggleFollow($id, 'app_member_view');
    }

    #[Route('/members/rotate-avatar/{id}', name: 'app_member_rotate_avatar')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function rotateProfileImage(int $id): Response
    {

        $user = $this->repo->findOneBy(['id' => $id]);
        if ($user->getImage() !== null) {
            $this->imageService->rotateThumbNail($user->getImage());
        }

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/remove-image/{id}', name: 'app_member_remove_avatar')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function removeProfileImage(EntityManagerInterface $em, int $id): Response
    {

        $user = $this->repo->findOneBy(['id' => $id]);
        $oldImageId = $user->getImage()?->getId();
        $user->setImage(null);
        $em->persist($user);
        $em->flush();

        if ($oldImageId !== null) {
            $this->imageLocationService->removeLocation($oldImageId, ImageType::ProfilePicture, $user->getId());
        }

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/restrict/{id}', name: 'app_member_restrict')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function restrictUser(EntityManagerInterface $em, int $id): Response
    {

        $user = $this->repo->findOneBy(['id' => $id]);
        $user->setRestricted(!$user->isRestricted());
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/verify/{id}', name: 'app_member_verify')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function verifyUser(EntityManagerInterface $em, int $id): Response
    {

        $user = $this->repo->findOneBy(['id' => $id]);
        $user->setVerified(!$user->isVerified());
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }

    #[Route('/members/takeover/{id}', name: 'app_member_takeover')]
    #[IsGranted('ROLE_ADMIN')]
    public function takeoverUser(int $id): Response
    {

        $user = $this->repo->findOneBy(['id' => $id]);
        $loginResponse = $this->security->login($user);

        return $loginResponse ?? $this->redirectToRoute('app_member_view', ['id' => $id]);
    }
}
