<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WallPost;
use App\Repository\WallPostRepository;
use App\Service\TownHall\TownHallAccessService;
use App\Service\TownHallService;
use App\Service\WallService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TownHallController extends AbstractController
{
    public function __construct(
        private readonly TownHallAccessService $accessService,
        private readonly TownHallService $townHallService,
        private readonly WallService $wallService,
        private readonly WallPostRepository $wallPostRepo,
    ) {}

    #[Route('/townhall', name: 'app_townhall', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function hub(): Response
    {
        $this->guardEnabled();

        $upcoming = $this->townHallService->getUpcomingEvents();

        return $this->render('town_hall/index.html.twig', [
            'wallPosts' => $this->townHallService->getRecentWallPosts(),
            'comments' => $this->townHallService->getLatestEventComments(),
            'images' => $this->townHallService->getLatestEventImages(),
            'upcomingEvents' => $upcoming,
            'pastEvents' => $this->townHallService->getRecentPastEvents(),
            'newMembers' => $this->townHallService->getNewMembersThisMonth(),
            'stats' => $this->townHallService->getStats(),
            'maxContentLength' => WallPost::MAX_CONTENT_LENGTH,
        ]);
    }

    #[Route('/townhall/wall/{postId}', name: 'app_townhall_wall_topic', methods: ['GET'], requirements: ['postId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function topic(int $postId): Response
    {
        $this->guardEnabled();

        $post = $this->wallPostRepo->find($postId);
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        $upcoming = $this->townHallService->getUpcomingEvents();

        return $this->render('town_hall/wall_topic.html.twig', [
            'post' => $post,
            'upcomingEvents' => $upcoming,
            'pastEvents' => $this->townHallService->getRecentPastEvents(),
            'comments' => $this->townHallService->getLatestEventComments(),
            'images' => $this->townHallService->getLatestEventImages(),
            'stats' => $this->townHallService->getStats(),
        ]);
    }

    #[Route('/townhall/gallery', name: 'app_townhall_gallery', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function gallery(): Response
    {
        $this->guardEnabled();

        return $this->render('town_hall/gallery.html.twig', [
            'images' => $this->townHallService->getAllEventImagesChronological(),
        ]);
    }

    #[Route('/townhall/wall', name: 'app_townhall_wall_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function postWall(Request $request): Response
    {
        $this->guardEnabled();
        $this->guardCsrf($request, 'town_hall_wall_post');

        $content = (string) $request->request->get('content', '');
        if (trim($content) === '' || mb_strlen($content) > WallPost::MAX_CONTENT_LENGTH) {
            $this->addFlash('error', 'town_hall.flash.wall_post_invalid');

            return $this->redirectToRoute('app_townhall', ['_locale' => $request->getLocale()]);
        }

        $this->wallService->createPost($this->getAuthedUser(), $content);

        $this->addFlash('success', 'town_hall.flash.wall_post_created');

        return $this->redirectToRoute('app_townhall', ['_locale' => $request->getLocale()]);
    }

    #[Route('/townhall/wall/{postId}/reply', name: 'app_townhall_wall_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function replyWall(Request $request, int $postId): Response
    {
        $this->guardEnabled();
        $this->guardCsrf($request, 'town_hall_wall_reply_' . $postId);

        $post = $this->wallPostRepo->find($postId);
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        $content = (string) $request->request->get('content', '');
        if (trim($content) === '') {
            $this->addFlash('error', 'town_hall.flash.wall_reply_invalid');
        } else {
            $this->wallService->createReply($post, $this->getAuthedUser(), $content);
            $this->addFlash('success', 'town_hall.flash.wall_reply_created');
        }

        return $this->redirectToRoute('app_townhall_wall_topic', [
            '_locale' => $request->getLocale(),
            'postId' => $postId,
        ]);
    }

    #[Route('/townhall/wall/{postId}/delete', name: 'app_townhall_wall_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteWallPost(Request $request, int $postId): Response
    {
        $this->guardEnabled();
        $this->guardCsrf($request, 'town_hall_wall_delete_' . $postId);

        $post = $this->wallPostRepo->find($postId);
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        $this->wallService->deletePost($post, $this->getAuthedUser());

        $this->addFlash('success', 'town_hall.flash.wall_post_deleted');

        return $this->redirectToRoute('app_townhall_wall', ['_locale' => $request->getLocale()]);
    }

    private function guardEnabled(): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $user = null;
        }
        if (!$this->accessService->canAccess($user)) {
            throw $this->createNotFoundException();
        }
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
