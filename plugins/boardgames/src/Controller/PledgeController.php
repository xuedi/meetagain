<?php declare(strict_types=1);

namespace Plugin\Boardgames\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Entity\Event;
use App\Repository\EventRepository;
use Plugin\Boardgames\Activity\Messages\GamePledged;
use Plugin\Boardgames\Activity\Messages\PledgeWithdrawn;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\PledgeService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boardgames/pledge')]
#[IsGranted('ROLE_USER')]
final class PledgeController extends AbstractController
{
    public function __construct(
        private readonly PledgeService $pledgeService,
        private readonly GameService $gameService,
        private readonly EventRepository $eventRepository,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/{eventId}', name: 'app_plugin_boardgames_pledge_create', requirements: ['eventId' => '\d+'], methods: ['POST'])]
    public function create(int $eventId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_pledge_create' . $eventId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $event = $this->eventRepository->find($eventId);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Event not found');
        }

        $game = $this->gameService->get($request->request->getInt('game_id'));
        if ($game === null) {
            throw $this->createNotFoundException('Board game not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->pledgeService->pledge($event, $game, $user);

            $this->activityService->log(GamePledged::TYPE, $user, [
                'event_id' => $eventId,
                'game_id' => $game->getId(),
                'game_name' => $game->getName(),
            ]);
            $this->addFlash('success', 'boardgames_tile.flash_pledged');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_event_details', ['id' => $eventId]);
    }

    #[Route('/{id}/withdraw', name: 'app_plugin_boardgames_pledge_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_pledge_withdraw' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $user = $this->getAuthedUser();
        $pledge = $this->pledgeService->findOwnedBy($id, $user);
        if ($pledge === null) {
            throw $this->createNotFoundException('Pledge not found');
        }

        $this->pledgeService->withdraw($pledge);

        $this->activityService->log(PledgeWithdrawn::TYPE, $user, [
            'event_id' => $pledge->getEvent()?->getId(),
            'game_id' => $pledge->getGame()?->getId(),
            'game_name' => $pledge->getGame()?->getName(),
        ]);
        $this->addFlash('success', 'boardgames_tile.flash_withdrawn');

        return $this->redirectToRoute('app_event_details', ['id' => $pledge->getEvent()?->getId()]);
    }
}
