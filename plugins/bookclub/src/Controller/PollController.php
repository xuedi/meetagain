<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Bookclub\Entity\SuggestionStatus;
use Plugin\Bookclub\Entity\ViewType;
use Plugin\Bookclub\Form\PollCreateType;
use Plugin\Bookclub\Repository\BookPollRepository;
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
        private readonly BookPollRepository $pollRepository,
    ) {}

    #[Route('/list', name: 'app_plugin_bookclub_poll_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): Response
    {
        $polls = $this->pollService->getAll();

        $winners = [];
        $events = [];
        foreach ($polls as $poll) {
            $winner = null;
            foreach ($poll->getSuggestions() as $suggestion) {
                if ($suggestion->getStatus() === SuggestionStatus::Selected) {
                    $winner = $suggestion;
                    break;
                }
            }
            $winners[$poll->getId()] = $winner;
            $events[$poll->getId()] = $this->eventRepository->find($poll->getEventId());
        }

        return $this->render('@Bookclub/poll/list.html.twig', [
            'polls' => $polls,
            'winners' => $winners,
            'events' => $events,
        ]);
    }

    #[Route('/set/view/{type}', name: 'app_plugin_bookclub_poll_set_view', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function setView(Request $request, ViewType $type): Response
    {
        $request->getSession()->set('bookclubPollViewType', $type->value);

        return $this->redirectToRoute('app_plugin_bookclub_poll');
    }

    #[Route('', name: 'app_plugin_bookclub_poll', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        $user = $this->getAuthedUser();
        $activePoll = $this->pollService->getActivePoll();
        $viewType = $request->getSession()->get('bookclubPollViewType', ViewType::Tiles->value);

        if ($activePoll !== null) {
            $userVote = $this->pollService->getUserVote($activePoll->getId(), $user->getId());
            $event = $this->eventRepository->find($activePoll->getEventId());

            return $this->render('@Bookclub/poll/vote.html.twig', [
                'poll' => $activePoll,
                'userVote' => $userVote,
                'event' => $event,
                'viewType' => $viewType,
            ]);
        }

        $latestClosed = $this->pollService->getLatestClosedPoll();
        if ($latestClosed !== null) {
            $results = $this->pollService->getResults($latestClosed->getId());
            $event = $this->eventRepository->find($latestClosed->getEventId());

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
        } catch (RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_bookclub_poll');
    }

    #[Route('/create', name: 'app_plugin_bookclub_poll_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function create(Request $request): Response
    {
        $pendingSuggestions = $this->suggestionService->getPendingSuggestionsWithPriority();
        $approvedBooks = $this->bookService->getApprovedList();
        $usedEventIds = $this->pollRepository->findUsedEventIds();
        $upcomingEvents = array_filter(
            $this->eventRepository->getUpcomingEvents(20),
            fn($event) => !in_array($event->getId(), $usedEventIds),
        );

        $preselectedEventId = $request->query->getInt('eventId') ?: null;

        $form = $this->createForm(PollCreateType::class, ['event_id' => $preselectedEventId], [
            'suggestions' => $pendingSuggestions,
            'books' => $approvedBooks,
            'events' => $upcomingEvents,
            'suggestion_service' => $this->suggestionService,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $suggestionIds = $form->get('suggestions')->getData() ?? [];
            $bookIds = $form->get('books')->getData() ?? [];
            $eventId = $form->get('event_id')->getData();

            $user = $this->getAuthedUser();

            try {
                $this->pollService->create($suggestionIds, $bookIds, $user->getId(), $eventId);
                $this->addFlash('success', 'Poll created. Members can now vote.');

                return $this->redirectToRoute('app_plugin_bookclub_poll');
            } catch (RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('@Bookclub/poll/create.html.twig', [
            'form' => $form,
            'suggestions' => $pendingSuggestions,
        ]);
    }

    #[Route('/{id}/results', name: 'app_plugin_bookclub_poll_results', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function results(int $id): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $results = $this->pollService->getResults($id);
        $event = $this->eventRepository->find($poll->getEventId());

        return $this->render('@Bookclub/poll/results.html.twig', [
            'poll' => $poll,
            'results' => $results,
            'event' => $event,
        ]);
    }

    #[Route('/{id}/close', name: 'app_plugin_bookclub_poll_close', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
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
