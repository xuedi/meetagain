<?php declare(strict_types=1);

namespace Plugin\Voting\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\ItemTypeRegistry;
use App\Repository\EventRepository;
use Plugin\Voting\Activity\Messages\PollClosed;
use Plugin\Voting\Activity\Messages\PollCreated;
use Plugin\Voting\Activity\Messages\VoteCast;
use Plugin\Voting\Entity\PollStatus;
use Plugin\Voting\Service\ConfigService;
use Plugin\Voting\Service\PollService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/voting/poll')]
final class PollController extends AbstractController
{
    public function __construct(
        private readonly PollService $pollService,
        private readonly ConfigService $config,
        private readonly EventRepository $eventRepo,
        private readonly ItemTypeRegistry $registry,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('', name: 'app_voting_poll_list', methods: ['GET'])]
    public function list(): Response
    {
        $activePolls = $this->pollService->getActivePolls();
        $closedPolls = $this->pollService->getClosedPolls();

        if ($activePolls === [] && $closedPolls === []) {
            return $this->render('@Voting/poll/none.html.twig');
        }

        return $this->render('@Voting/poll/list.html.twig', [
            'activePolls' => $activePolls,
            'closedPolls' => $closedPolls,
        ]);
    }

    #[Route('/create/{eventId}/{itemType}', name: 'app_voting_poll_create', methods: ['GET', 'POST'], requirements: ['eventId' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function create(int $eventId, string $itemType, Request $request): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null || !$this->registry->has($itemType)) {
            throw $this->createNotFoundException('Event or item type not found');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('voting_poll_create' . $eventId, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            $user = $this->getAuthedUser();
            $itemIds = array_values(array_map('intval', $request->request->all('items')));
            $durationDays = max(1, (int) $request->request->get('durationDays', $this->config->getConfig()->getDefaultDurationDays()));

            try {
                $poll = $this->pollService->create($event, $itemType, $itemIds, $durationDays, $user->getId());
                $this->activityService->log(PollCreated::TYPE, $user, [
                    'poll_id' => $poll->getId(),
                    'event_id' => $eventId,
                ]);
                $this->addFlash('success', 'voting_poll.flash_created');

                return $this->redirectToRoute('app_voting_poll_show', ['id' => $poll->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $provider = $this->registry->providerFor($itemType);

        return $this->render('@Voting/poll/create.html.twig', [
            'event' => $event,
            'itemType' => $itemType,
            'itemTypeLabelKey' => $provider?->getLabelKey(),
            'candidateItemIds' => $this->pollService->getCandidateItemIds($itemType),
            'defaultDurationDays' => $this->config->getConfig()->getDefaultDurationDays(),
        ]);
    }

    #[Route('/{id}', name: 'app_voting_poll_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $userVotedItemIds = [];
        if ($this->isGranted('ROLE_USER')) {
            $userVotedItemIds = $this->pollService->getUserVotedItemIds($poll, $this->getAuthedUser()->getId());
        }

        return $this->render('@Voting/poll/results.html.twig', [
            'poll' => $poll,
            'voteCounts' => $this->pollService->getVoteCounts($poll),
            'userVotedItemIds' => $userVotedItemIds,
        ]);
    }

    #[Route('/{id}/vote', name: 'app_voting_poll_vote', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function vote(int $id, Request $request): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $user = $this->getAuthedUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('voting_poll_vote' . $id, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            $selectedIds = array_values(array_map('intval', $request->request->all('items')));

            try {
                $this->pollService->castVote($user->getId(), $poll, $selectedIds);
                $this->activityService->log(VoteCast::TYPE, $user, ['poll_id' => $poll->getId()]);
                $this->addFlash('success', 'voting_poll.flash_voted');

                return $this->redirectToRoute('app_voting_poll_show', ['id' => $id]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Voting/poll/vote.html.twig', [
            'poll' => $poll,
            'userVotedItemIds' => $this->pollService->getUserVotedItemIds($poll, $user->getId()),
            'singleChoice' => $this->config->getConfig()->isSingleChoice(),
        ]);
    }

    #[Route('/{id}/close', name: 'app_voting_poll_close', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function close(int $id, Request $request): Response
    {
        $poll = $this->pollService->get($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Poll not found');
        }

        $user = $this->getAuthedUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('voting_poll_close' . $id, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            $chosenItemId = (int) $request->request->get('chosen_item_id');
            if (!in_array($chosenItemId, $poll->getOptionItemIds(), true)) {
                $this->addFlash('error', 'voting_poll.flash_invalid_choice');

                return $this->redirectToRoute('app_voting_poll_close', ['id' => $id]);
            }

            try {
                if ($poll->getStatus() === PollStatus::Active) {
                    $this->pollService->close($poll);
                }
                $this->pollService->commitOutcome($poll, $chosenItemId);
                $this->activityService->log(PollClosed::TYPE, $user, ['poll_id' => $poll->getId()]);
                $this->addFlash('success', 'voting_poll.flash_closed');

                return $this->redirectToRoute('app_voting_poll_show', ['id' => $id]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $tiedItemIds = $poll->getTiedItemIds() ?? $poll->getOptionItemIds();

        return $this->render('@Voting/poll/close.html.twig', [
            'poll' => $poll,
            'tiedItemIds' => $tiedItemIds,
            'voteCounts' => $this->pollService->getVoteCounts($poll),
        ]);
    }
}
