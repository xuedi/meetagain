<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Bookclub\Activity\Messages\SuggestionCreated;
use Plugin\Bookclub\Activity\Messages\SuggestionWithdrawn;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\SuggestionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub')]
#[IsGranted('ROLE_USER')]
final class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly SuggestionService $suggestionService,
        private readonly BookService $bookService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/suggestions', name: 'app_plugin_bookclub_suggestions', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getAuthedUser();

        $userSuggestions = $this->suggestionService->getUserPendingSuggestions($user->getId());
        $suggestedBookIds = array_map(static fn($s) => $s->getBook()->getId(), $userSuggestions);

        return $this->render('@Bookclub/suggestion/list.html.twig', [
            'suggestions' => $this->suggestionService->getPendingSuggestionsWithPriority(),
            'userSuggestions' => $userSuggestions,
            'books' => array_filter(
                $this->bookService->getApprovedList(),
                static fn($b) => !in_array($b->getId(), $suggestedBookIds),
            ),
        ]);
    }

    #[Route('/suggest/{bookId}', name: 'app_plugin_bookclub_suggest', methods: ['POST'])]
    public function suggest(int $bookId, Request $request): Response
    {
        $book = $this->bookService->get($bookId);
        if ($book === null || !$book->isApproved()) {
            throw $this->createNotFoundException('Book not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->suggestionService->suggest($book, $user->getId());
            $this->activityService->log(SuggestionCreated::TYPE, $user, [
                'book_id' => $book->getId(),
                'book_title' => $book->getTitle(),
            ]);
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if ($request->request->get('redirect') === 'book') {
            return $this->redirectToRoute('app_plugin_bookclub_book_show', ['id' => $bookId]);
        }

        return $this->redirectToRoute('app_plugin_bookclub_suggestions');
    }

    #[Route('/withdraw/{suggestionId}', name: 'app_plugin_bookclub_withdraw', methods: ['GET'])]
    public function withdraw(int $suggestionId): Response
    {
        $user = $this->getAuthedUser();
        $suggestion = $this->suggestionService->get($suggestionId);

        try {
            $this->suggestionService->withdraw($suggestionId, $user->getId());
            if ($suggestion !== null) {
                $this->activityService->log(SuggestionWithdrawn::TYPE, $user, [
                    'book_id' => $suggestion->getBook()->getId(),
                    'book_title' => $suggestion->getBook()->getTitle(),
                ]);
            }
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_suggestions');
    }
}
