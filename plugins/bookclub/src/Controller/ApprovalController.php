<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Bookclub\Activity\Messages\BookApproved;
use Plugin\Bookclub\Activity\Messages\BookRejected;
use Plugin\Bookclub\Service\BookService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub/manage')]
#[IsGranted('ROLE_ORGANIZER')]
final class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly BookService $bookService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/pending', name: 'app_plugin_bookclub_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Bookclub/manage/pending.html.twig', [
            'books' => $this->bookService->getPendingList(),
        ]);
    }

    #[Route('/approve/{id}', name: 'app_plugin_bookclub_approve', methods: ['POST'])]
    public function approve(int $id): Response
    {
        $book = $this->bookService->get($id);

        try {
            $this->bookService->approve($id);
            if ($book !== null) {
                $this->activityService->log(BookApproved::TYPE, $this->getAuthedUser(), [
                    'book_id' => $book->getId(),
                    'book_title' => $book->getTitle(),
                ]);
            }
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_pending');
    }

    #[Route('/reject/{id}', name: 'app_plugin_bookclub_reject', methods: ['POST'])]
    public function reject(int $id): Response
    {
        $book = $this->bookService->get($id);

        try {
            $this->bookService->reject($id);
            $this->addFlash('success', 'Book rejected and removed.');
            if ($book !== null) {
                $this->activityService->log(BookRejected::TYPE, $this->getAuthedUser(), [
                    'book_id' => $book->getId(),
                    'book_title' => $book->getTitle(),
                ]);
            }
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_pending');
    }
}
