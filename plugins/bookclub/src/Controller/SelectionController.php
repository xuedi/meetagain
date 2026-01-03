<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\SelectionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub/manage')]
#[IsGranted('ROLE_MANAGER')]
class SelectionController extends AbstractController
{
    public function __construct(
        private readonly SelectionService $selectionService,
        private readonly BookService $bookService,
        private readonly EventRepository $eventRepository,
    ) {}

    #[Route('/select/{eventId}', name: 'app_plugin_bookclub_select', methods: ['GET'])]
    public function selectForm(int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $existingSelection = $this->selectionService->getByEvent($event);

        return $this->render('@Bookclub/manage/select.html.twig', [
            'event' => $event,
            'books' => $this->bookService->getApprovedList(),
            'currentSelection' => $existingSelection,
        ]);
    }

    #[Route('/select/{eventId}', name: 'app_plugin_bookclub_select_submit', methods: ['POST'])]
    public function select(int $eventId, Request $request): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $bookId = (int) $request->request->get('book_id');
        $book = $this->bookService->get($bookId);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->selectionService->select($book, $event, $user->getId());
            $this->addFlash('success', 'Book assigned to event.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
    }

    #[Route('/unselect/{selectionId}', name: 'app_plugin_bookclub_unselect', methods: ['POST'])]
    public function unselect(int $selectionId): Response
    {
        $selection = $this->selectionService->get($selectionId);
        if ($selection === null) {
            throw $this->createNotFoundException('Selection not found');
        }

        $eventId = $selection->getEvent()->getId();
        $this->selectionService->remove($selectionId);
        $this->addFlash('success', 'Book removed from event.');

        return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
    }
}
