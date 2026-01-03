<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Bookclub\Form\PollCreateType;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\PollService;
use Plugin\Bookclub\Service\SuggestionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub/poll')]
class PollController extends AbstractController
{
    public function __construct(
        private readonly PollService $pollService,
        private readonly SuggestionService $suggestionService,
        private readonly BookService $bookService,
        private readonly EventRepository $eventRepository,
    ) {}

    #[Route('', name: 'app_plugin_bookclub_poll', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getAuthedUser();
        $activePoll = $this->pollService->getActivePoll();

        if ($activePoll !== null) {
            $userVote = $this->pollService->getUserVote($activePoll->getId(), $user->getId());
            $event = null;
            if ($activePoll->getEventId() !== null) {
                $event = $this->eventRepository->find($activePoll->getEventId());
            }

            return $this->render('@Bookclub/poll/vote.html.twig', [
                'poll' => $activePoll,
                'userVote' => $userVote,
                'event' => $event,
            ]);
        }

        $latestClosed = $this->pollService->getLatestClosedPoll();
        if ($latestClosed !== null) {
            $results = $this->pollService->getResults($latestClosed->getId());
            $event = null;
            if ($latestClosed->getEventId() !== null) {
                $event = $this->eventRepository->find($latestClosed->getEventId());
            }

            return $this->render('@Bookclub/poll/results.html.twig', [
                'poll' => $latestClosed,
                'results' => $results,
                'event' => $event,
            ]);
        }

        return $this->render('@Bookclub/poll/none.html.twig');
    }

    #[Route('/vote/{suggestionId}', name: 'app_plugin_bookclub_poll_vote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(int $suggestionId): Response
    {
        $activePoll = $this->pollService->getActivePoll();
        if ($activePoll === null) {
            $this->addFlash('warning', 'No active poll.');
            return $this->redirectToRoute('app_plugin_bookclub_poll');
        }

        $user = $this->getAuthedUser();

        try {
            $this->pollService->vote($activePoll->getId(), $suggestionId, $user->getId());
            $this->addFlash('success', 'Vote recorded.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_poll');
    }

    #[Route('/create', name: 'app_plugin_bookclub_poll_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function create(Request $request): Response
    {
        $pendingSuggestions = $this->suggestionService->getPendingSuggestionsWithPriority();
        $approvedBooks = $this->bookService->getApprovedList();
        $upcomingEvents = $this->eventRepository->getUpcomingEvents(20);

        $form = $this->createForm(PollCreateType::class, null, [
            'suggestions' => $pendingSuggestions,
            'books' => $approvedBooks,
            'events' => $upcomingEvents,
            'suggestion_service' => $this->suggestionService,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $title = $form->get('title')->getData();
            $suggestionIds = $form->get('suggestions')->getData() ?? [];
            $bookIds = $form->get('books')->getData() ?? [];
            $eventId = $form->get('event_id')->getData();

            $user = $this->getAuthedUser();

            try {
                $poll = $this->pollService->create($title, $suggestionIds, $bookIds, $user->getId(), $eventId);
                $this->addFlash('success', 'Poll created as draft.');

                return $this->redirectToRoute('app_plugin_bookclub_poll_manage', ['id' => $poll->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('@Bookclub/poll/create.html.twig', [
            'form' => $form,
            'suggestions' => $pendingSuggestions,
        ]);
    }

    #[Route('/manage/{id}', name: 'app_plugin_bookclub_poll_manage', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function manage(int $id): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $event = null;
        if ($poll->getEventId() !== null) {
            $event = $this->eventRepository->find($poll->getEventId());
        }

        return $this->render('@Bookclub/poll/manage.html.twig', [
            'poll' => $poll,
            'event' => $event,
        ]);
    }

    #[Route('/{id}/activate', name: 'app_plugin_bookclub_poll_activate', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function activate(int $id): Response
    {
        try {
            $this->pollService->activate($id);
            $this->addFlash('success', 'Poll activated. Members can now vote.');
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_poll');
    }

    #[Route('/{id}/close', name: 'app_plugin_bookclub_poll_close', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function close(int $id): Response
    {
        try {
            $winner = $this->pollService->close($id);
            $this->addFlash('success', 'Poll closed. Winner: ' . $winner->getBook()->getTitle());
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_poll');
    }
}
