<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\SuggestionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub')]
#[IsGranted('ROLE_USER')]
class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly SuggestionService $suggestionService,
        private readonly BookService $bookService,
    ) {}

    #[Route('/suggestions', name: 'app_plugin_bookclub_suggestions', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getAuthedUser();

        return $this->render('@Bookclub/suggestion/list.html.twig', [
            'suggestions' => $this->suggestionService->getPendingSuggestionsWithPriority(),
            'userSuggestion' => $this->suggestionService->getUserPendingSuggestion($user->getId()),
            'userRejected' => $this->suggestionService->getUserRejectedSuggestions($user->getId()),
            'books' => $this->bookService->getApprovedList(),
        ]);
    }

    #[Route('/suggest/{bookId}', name: 'app_plugin_bookclub_suggest', methods: ['POST'])]
    public function suggest(int $bookId): Response
    {
        $book = $this->bookService->get($bookId);
        if ($book === null || !$book->isApproved()) {
            throw $this->createNotFoundException('Book not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->suggestionService->suggest($book, $user->getId());
            $this->addFlash('success', 'Book suggested for the next poll.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_suggestions');
    }

    #[Route('/resubmit/{suggestionId}', name: 'app_plugin_bookclub_resubmit', methods: ['POST'])]
    public function resubmit(int $suggestionId): Response
    {
        $user = $this->getAuthedUser();

        try {
            $this->suggestionService->resubmit($suggestionId, $user->getId());
            $this->addFlash('success', 'Book resubmitted for the next poll.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_suggestions');
    }

    #[Route('/withdraw/{suggestionId}', name: 'app_plugin_bookclub_withdraw', methods: ['POST'])]
    public function withdraw(int $suggestionId): Response
    {
        $user = $this->getAuthedUser();

        try {
            $this->suggestionService->withdraw($suggestionId, $user->getId());
            $this->addFlash('success', 'Suggestion withdrawn.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_suggestions');
    }
}
