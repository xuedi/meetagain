<?php

declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Filmclub\Activity\Messages\PollClosed;
use Plugin\Filmclub\Activity\Messages\PollCreated;
use Plugin\Filmclub\Activity\Messages\PollVoteCast;
use Plugin\Filmclub\Form\PollCreateType;
use Plugin\Filmclub\Service\PollService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/poll')]
final class PollController extends AbstractController
{
    public function __construct(
        private readonly PollService $pollService,
        private readonly EventRepository $eventRepo,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('', name: 'app_plugin_filmclub_poll_list', methods: ['GET'])]
    public function list(): Response
    {
        $activePolls = $this->pollService->getActivePolls();
        $closedPolls = $this->pollService->getClosedPolls();

        if ($activePolls === [] && $closedPolls === []) {
            return $this->render('@Filmclub/poll/none.html.twig');
        }

        return $this->render('@Filmclub/poll/list.html.twig', [
            'activePolls' => $activePolls,
            'closedPolls' => $closedPolls,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_filmclub_poll_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $voteCounts = $this->pollService->getVoteCounts($poll);
        $userVotedSuggestionIds = [];
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getAuthedUser();
            foreach ($this->pollService->getUserVotes($poll, $user->getId()) as $vote) {
                $userVotedSuggestionIds[] = $vote->getSuggestion()?->getId();
            }
        }

        return $this->render('@Filmclub/poll/results.html.twig', [
            'poll' => $poll,
            'voteCounts' => $voteCounts,
            'userVotedSuggestionIds' => $userVotedSuggestionIds,
            'totalVoters' => $this->pollService->hasUserVoted($poll, 0) ? 0 : 0,
        ]);
    }

    #[Route('/create/{eventId}', name: 'app_plugin_filmclub_poll_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function create(int $eventId, Request $request): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $pendingSuggestions = $this->pollService->getPendingSuggestionsForPoll();

        $form = $this->createForm(PollCreateType::class, null, [
            'available_suggestions' => $pendingSuggestions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $durationDays = (int) $form->get('durationDays')->getData();

                $poll = $this->pollService->create(
                    $eventId,
                    $form->get('suggestions')->getData()->toArray(),
                    $durationDays,
                    $user->getId(),
                );

                $this->activityService->log(PollCreated::TYPE, $user, [
                    'poll_id' => $poll->getId(),
                    'event_id' => $eventId,
                ]);

                $this->addFlash('success', 'filmclub_poll.flash_created');

                return $this->redirectToRoute('app_plugin_filmclub_poll_show', ['id' => $poll->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Filmclub/poll/create.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/vote', name: 'app_plugin_filmclub_poll_vote', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(int $id, Request $request): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $user = $this->getAuthedUser();
        $userVotes = $this->pollService->getUserVotes($poll, $user->getId());
        $userVotedIds = array_map(static fn($v) => $v->getSuggestion()?->getId(), $userVotes);

        if ($request->isMethod('POST')) {
            $selectedIds = array_map('intval', $request->request->all('suggestions'));

            $selectedSuggestions = array_values(array_filter($poll->getSuggestions()->toArray(), static fn($s) => in_array(
                $s->getId(),
                $selectedIds,
                true,
            )));

            try {
                $this->pollService->castVote($user->getId(), $poll, $selectedSuggestions);
                $this->activityService->log(PollVoteCast::TYPE, $user, [
                    'poll_id' => $poll->getId(),
                ]);
                $this->addFlash('success', 'filmclub_poll.flash_voted');

                return $this->redirectToRoute('app_plugin_filmclub_poll_show', ['id' => $id]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Filmclub/poll/vote.html.twig', [
            'poll' => $poll,
            'userVotedIds' => $userVotedIds,
        ]);
    }

    #[Route('/{id}/close', name: 'app_plugin_filmclub_poll_close', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function close(int $id, Request $request): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $user = $this->getAuthedUser();

        if ($request->isMethod('POST')) {
            $chosenId = (int) $request->request->get('chosen_suggestion_id');

            $chosen = null;
            foreach ($poll->getSuggestions() as $suggestion) {
                if ($suggestion->getId() !== $chosenId) {
                    continue;
                }

                $chosen = $suggestion;
                break;
            }

            if ($chosen === null) {
                $this->addFlash('error', 'filmclub_poll.flash_invalid_choice');

                return $this->redirectToRoute('app_plugin_filmclub_poll_close', ['id' => $id]);
            }

            if ($poll->getStatus()->value === 1) {
                $this->pollService->close($poll);
            }

            try {
                $this->pollService->commitOutcome($poll, $chosen);
                $this->activityService->log(PollClosed::TYPE, $user, ['poll_id' => $poll->getId()]);
                $this->addFlash('success', 'filmclub_poll.flash_closed');

                return $this->redirectToRoute('app_plugin_filmclub_poll_show', ['id' => $id]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $tiedSuggestions = [];
        if ($poll->getTiedSuggestions() !== null) {
            foreach ($poll->getSuggestions() as $suggestion) {
                if (!in_array($suggestion->getId(), $poll->getTiedSuggestions(), true)) {
                    continue;
                }

                $tiedSuggestions[] = $suggestion;
            }
        } else {
            $tiedSuggestions = $poll->getSuggestions()->toArray();
        }

        $voteCounts = $this->pollService->getVoteCounts($poll);

        return $this->render('@Filmclub/poll/close.html.twig', [
            'poll' => $poll,
            'tiedSuggestions' => $tiedSuggestions,
            'voteCounts' => $voteCounts,
        ]);
    }
}
