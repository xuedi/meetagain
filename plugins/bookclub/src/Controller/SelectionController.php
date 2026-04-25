<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Plugin\Bookclub\Repository\BookPollRepository;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\PollService;
use Plugin\Bookclub\Service\SelectionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/bookclub/manage')]
#[IsGranted('ROLE_ORGANIZER')]
final class SelectionController extends AbstractController
{
    public function __construct(
        private readonly SelectionService $selectionService,
        private readonly BookService $bookService,
        private readonly EventRepository $eventRepository,
        private readonly BookPollRepository $pollRepository,
        private readonly PollService $pollService,
        private readonly UserRepository $userRepository,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/select/{eventId}', name: 'app_plugin_bookclub_select', methods: ['GET'])]
    public function selectForm(int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $currentSelection = $this->selectionService->getByEvent($event);

        $poll = $this->pollRepository->findByEventId($eventId);
        $pollResults = null;
        $votesByUser = [];
        if ($poll !== null) {
            $pollResults = $this->pollService->getResults($poll->getId());
            foreach ($poll->getVotes() as $vote) {
                $user = $this->userRepository->find($vote->getUserId());
                $votesByUser[] = [
                    'userName' => $user?->getName() ?? 'Unknown',
                    'bookTitle' => $vote->getSuggestion()->getBook()->getTitle(),
                    'suggestionId' => $vote->getSuggestion()->getId(),
                ];
            }
        }

        return $this->render('@Bookclub/manage/select.html.twig', [
            'event' => $event,
            'books' => $this->bookService->getApprovedList(),
            'currentSelection' => $currentSelection,
            'poll' => $poll,
            'pollResults' => $pollResults,
            'votesByUser' => $votesByUser,
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
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
    }

    #[Route('/select-winner/{eventId}', name: 'app_plugin_bookclub_select_winner', methods: ['POST'])]
    public function selectWinner(int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $poll = $this->pollRepository->findByEventId($eventId);
        if ($poll === null) {
            $this->addFlash('error', $this->translator->trans('bookclub_manage.flash_no_poll'));
            return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
        }

        $results = $this->pollService->getResults($poll->getId());
        $winner = $results['winner'];
        if ($winner === null) {
            $this->addFlash('error', $this->translator->trans('bookclub_manage.flash_no_winner'));
            return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
        }

        $user = $this->getAuthedUser();

        try {
            $this->selectionService->select($winner->getBook(), $event, $user->getId());
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
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

        return $this->redirectToRoute('app_plugin_bookclub_select', ['eventId' => $eventId]);
    }
}
