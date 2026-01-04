<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Filmclub\Entity\Vote;
use Plugin\Filmclub\Entity\VoteBallot;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\VoteBallotRepository;
use Plugin\Filmclub\Repository\VoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vote')]
class VoteController extends AbstractController
{
    public function __construct(
        private readonly VoteRepository $voteRepository,
        private readonly VoteBallotRepository $voteBallotRepository,
        private readonly FilmRepository $filmRepository,
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('/', name: 'app_filmclub_vote', methods: ['GET'])]
    public function index(): Response
    {
        $openVotes = $this->voteRepository->findOpenVotes();
        $closedVotes = $this->voteRepository->findClosedVotes();

        $eventIds = array_unique(array_merge(
            array_map(fn(Vote $v) => $v->getEventId(), $openVotes),
            array_map(fn(Vote $v) => $v->getEventId(), $closedVotes),
        ));

        $events = [];
        foreach ($eventIds as $eventId) {
            $events[$eventId] = $this->eventRepository->find($eventId);
        }

        return $this->render('@Filmclub/vote/index.html.twig', [
            'openVotes' => $openVotes,
            'closedVotes' => $closedVotes,
            'nextEvent' => $this->getNextEvent(),
            'events' => $events,
        ]);
    }

    #[Route('/create/{eventId}', name: 'app_filmclub_vote_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function create(int $eventId, Request $request): Response
    {
        $event = $this->eventRepository->find($eventId);
        if (!$event) {
            $this->addFlash('error', 'Event not found');
            return $this->redirectToRoute('app_filmclub_vote');
        }

        $existingVote = $this->voteRepository->findByEventId($eventId);
        if ($existingVote) {
            $this->addFlash('error', 'A vote already exists for this event');
            return $this->redirectToRoute('app_filmclub_vote');
        }

        if ($request->isMethod('POST')) {
            $closesAt = $request->request->get('closes_at');
            if (!$closesAt) {
                $this->addFlash('error', 'Please provide a closing date');
                return $this->redirectToRoute('app_filmclub_vote_create', ['eventId' => $eventId]);
            }

            $vote = new Vote();
            $vote->setEventId($eventId);
            $vote->setClosesAt(new \DateTimeImmutable($closesAt));
            $vote->setCreatedAt(new \DateTimeImmutable());
            $vote->setCreatedBy($this->getAuthedUser()->getId());

            $this->voteRepository->save($vote, true);

            $this->addFlash('success', 'Vote created successfully');
            return $this->redirectToRoute('app_filmclub_vote');
        }

        return $this->render('@Filmclub/vote/create.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}', name: 'app_filmclub_vote_show', methods: ['GET'])]
    public function show(Vote $vote): Response
    {
        $event = $this->eventRepository->find($vote->getEventId());
        $userBallot = null;

        if ($this->getUser() !== null) {
            $userBallot = $this->voteBallotRepository->findByVoteAndMember($vote, $this->getAuthedUser()->getId());
        }

        return $this->render('@Filmclub/vote/show.html.twig', [
            'vote' => $vote,
            'event' => $event,
            'films' => $this->filmRepository->findAll(),
            'userBallot' => $userBallot,
        ]);
    }

    #[Route('/{id}/cast', name: 'app_filmclub_vote_cast', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cast(Vote $vote, Request $request): Response
    {
        if (!$vote->isVotingOpen()) {
            $this->addFlash('error', 'Voting is closed');
            return $this->redirectToRoute('app_filmclub_vote_show', ['id' => $vote->getId()]);
        }

        $user = $this->getAuthedUser();
        $existingBallot = $this->voteBallotRepository->findByVoteAndMember($vote, $user->getId());

        if ($existingBallot) {
            $this->addFlash('error', 'You have already voted');
            return $this->redirectToRoute('app_filmclub_vote_show', ['id' => $vote->getId()]);
        }

        $filmId = $request->request->getInt('film_id');
        $film = $this->filmRepository->find($filmId);

        if (!$film) {
            $this->addFlash('error', 'Please select a valid film');
            return $this->redirectToRoute('app_filmclub_vote_show', ['id' => $vote->getId()]);
        }

        $ballot = new VoteBallot();
        $ballot->setVote($vote);
        $ballot->setFilm($film);
        $ballot->setMemberId($user->getId());
        $ballot->setCreatedAt(new \DateTimeImmutable());

        $this->voteBallotRepository->save($ballot, true);

        $this->addFlash('success', 'Your vote has been recorded');
        return $this->redirectToRoute('app_filmclub_vote_show', ['id' => $vote->getId()]);
    }

    #[Route('/{id}/close', name: 'app_filmclub_vote_close', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function close(Vote $vote): Response
    {
        $vote->setIsClosed(true);
        $this->voteRepository->save($vote, true);

        $this->addFlash('success', 'Vote has been closed');
        return $this->redirectToRoute('app_filmclub_vote_show', ['id' => $vote->getId()]);
    }

    private function getNextEvent(): ?\App\Entity\Event
    {
        $nextEventId = $this->eventRepository->getNextEventId();
        if ($nextEventId === null) {
            return null;
        }

        return $this->eventRepository->find($nextEventId);
    }
}
