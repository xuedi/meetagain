<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Topic;
use App\Entity\User;
use App\Service\TownHall\AccessService;
use App\Service\TownHall\InvalidTopicException;
use App\Service\TownHall\TileCollector;
use App\Service\TownHall\TopicService;
use App\Service\TownHallService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TownHallController extends AbstractController
{
    public function __construct(
        private readonly AccessService $accessService,
        private readonly TownHallService $townHallService,
        private readonly TopicService $topicService,
        private readonly TileCollector $tileCollector,
    ) {}

    #[Route('/townhall', name: 'app_townhall', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function hub(): Response
    {
        $this->guardEnabled();

        return $this->render('town_hall/index.html.twig', [
            'conversations' => $this->townHallService->getLatestConversations(),
            'images' => $this->townHallService->getLatestEventImages(4),
            'upcomingEvents' => $this->townHallService->getUpcomingEvents(),
            'pastEvents' => $this->townHallService->getRecentPastEvents(),
            'newMembers' => $this->townHallService->getNewMembersThisMonth(),
            'stats' => $this->townHallService->getStats(),
            'tiles' => $this->tileCollector->collectByLocation(),
        ]);
    }

    #[Route('/townhall/forum', name: 'app_townhall_forum', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function forum(): Response
    {
        $this->guardEnabled();

        return $this->render('town_hall/forum.html.twig', [
            ...$this->treeContext(null),
            'activity' => $this->topicService->getRecentActivity(),
        ]);
    }

    #[Route('/townhall/forum/new', name: 'app_townhall_forum_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function newTopic(Request $request): Response
    {
        $this->guardEnabled();

        $parentId = $request->query->getInt('parent');
        $parent = $parentId > 0 ? $this->topicService->get($parentId) : null;
        if ($parentId > 0 && !$parent instanceof Topic) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $this->guardCsrf($request, 'town_hall_topic_new');

            try {
                $topic = $this->topicService->create($this->getAuthedUser(), (string) $request->request->get('title', ''), $parent);
            } catch (InvalidTopicException $exception) {
                $this->addFlash('error', $this->reasonFlashKey($exception));

                return $this->redirectToRoute('app_townhall_forum_new', $this->localeWith($request, $parent === null ? [] : ['parent' => $parent->getId()]));
            }

            $this->addFlash('success', 'town_hall.flash_topic_created');

            return $this->redirectToRoute('app_townhall_forum_topic', $this->localeWith($request, ['topicId' => $topic->getId()]));
        }

        return $this->render('town_hall/forum_new.html.twig', [
            ...$this->treeContext($parent),
            'parent' => $parent,
        ]);
    }

    #[Route('/townhall/forum/{topicId}', name: 'app_townhall_forum_topic', requirements: ['topicId' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function topic(int $topicId): Response
    {
        $this->guardEnabled();

        $topic = $this->requireTopic($topicId);
        $actor = $this->getAuthedUser();

        return $this->render('town_hall/forum_topic.html.twig', [
            ...$this->treeContext($topic),
            'topic' => $topic,
            'breadcrumb' => $this->topicService->ancestors($topic),
            'canRename' => $this->topicService->canRename($topic, $actor),
            'canDelete' => $this->topicService->canDelete($topic, $actor),
        ]);
    }

    #[Route('/townhall/forum/{topicId}/rename', name: 'app_townhall_forum_rename', requirements: ['topicId' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function renameTopic(Request $request, int $topicId): Response
    {
        $this->guardEnabled();
        $this->guardCsrf($request, 'town_hall_topic_rename_' . $topicId);

        $topic = $this->requireTopic($topicId);

        try {
            $this->topicService->rename($topic, $this->getAuthedUser(), (string) $request->request->get('title', ''));
            $this->addFlash('success', 'town_hall.flash_topic_renamed');
        } catch (InvalidTopicException $exception) {
            $this->addFlash('error', $this->reasonFlashKey($exception));
        }

        return $this->redirectToRoute('app_townhall_forum_topic', $this->localeWith($request, ['topicId' => $topicId]));
    }

    #[Route('/townhall/forum/{topicId}/delete', name: 'app_townhall_forum_delete', requirements: ['topicId' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteTopic(Request $request, int $topicId): Response
    {
        $this->guardEnabled();
        $this->guardCsrf($request, 'town_hall_topic_delete_' . $topicId);

        $topic = $this->requireTopic($topicId);
        $parent = $topic->getParent();

        $this->topicService->delete($topic, $this->getAuthedUser());
        $this->addFlash('success', 'town_hall.flash_topic_deleted');

        if ($parent instanceof Topic) {
            return $this->redirectToRoute('app_townhall_forum_topic', $this->localeWith($request, ['topicId' => $parent->getId()]));
        }

        return $this->redirectToRoute('app_townhall_forum', $this->localeWith($request, []));
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

    /**
     * @return array{childrenByParent: array<int, list<Topic>>, commentCounts: array<int, int>, activeId: ?int, maxDepth: int, maxTitleLength: int}
     */
    private function treeContext(?Topic $active): array
    {
        return [
            'childrenByParent' => $this->topicService->getChildrenByParent(),
            'commentCounts' => $this->topicService->getCommentCounts(),
            'activeId' => $active?->getId(),
            'maxDepth' => Topic::MAX_DEPTH,
            'maxTitleLength' => Topic::MAX_TITLE_LENGTH,
        ];
    }

    private function requireTopic(int $topicId): Topic
    {
        $topic = $this->topicService->get($topicId);
        if (!$topic instanceof Topic) {
            throw $this->createNotFoundException();
        }

        return $topic;
    }

    private function reasonFlashKey(InvalidTopicException $exception): string
    {
        return match ($exception->reason) {
            InvalidTopicException::REASON_TITLE_TOO_LONG => 'town_hall.flash_title_too_long',
            InvalidTopicException::REASON_TOO_DEEP => 'town_hall.flash_topic_too_deep',
            default => 'town_hall.flash_title_empty',
        };
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function localeWith(Request $request, array $parameters): array
    {
        return ['_locale' => $request->getLocale(), ...$parameters];
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
