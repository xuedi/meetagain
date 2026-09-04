<?php declare(strict_types=1);

namespace Plugin\Boardgames\Controller;

use App\Controller\AbstractController;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Service\BringRequestService;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\ShelfService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boardgames/request')]
#[IsGranted('ROLE_USER')]
final class RequestController extends AbstractController
{
    public function __construct(
        private readonly BringRequestService $requestService,
        private readonly GameService $gameService,
        private readonly ShelfService $shelfService,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/ask/{eventId}', name: 'app_plugin_boardgames_request_ask', requirements: ['eventId' => '\d+'], methods: ['GET'])]
    public function ask(int $eventId, Request $request): Response
    {
        $event = $this->requireEvent($eventId);
        $term = trim((string) $request->query->get('q', ''));

        $candidates = [];
        if ($term !== '') {
            foreach ($this->gameService->search($term) as $game) {
                $owners = array_values(array_filter(
                    $this->shelfService->getAskableOwners($game),
                    fn($ownership): bool => $ownership->getUser()?->getId() !== $this->getAuthedUser()->getId(),
                ));
                if ($owners !== []) {
                    $candidates[] = ['game' => $game, 'owners' => $owners];
                }
            }
        }

        return $this->render('@Boardgames/tile/ask.html.twig', [
            'event' => $event,
            'term' => $term,
            'candidates' => $candidates,
        ]);
    }

    #[Route('/create/{eventId}', name: 'app_plugin_boardgames_request_create', requirements: ['eventId' => '\d+'], methods: ['POST'])]
    public function create(int $eventId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_request_create' . $eventId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $event = $this->requireEvent($eventId);
        $game = $this->gameService->get($request->request->getInt('game_id'));
        $owner = $this->userRepository->find($request->request->getInt('owner_id'));

        if ($game === null || !$owner instanceof User) {
            throw $this->createNotFoundException('Board game or owner not found');
        }

        $message = trim((string) $request->request->get('message', ''));

        try {
            $this->requestService->ask($event, $game, $this->getAuthedUser(), $owner, $message === '' ? null : $message);
            $this->addFlash('success', 'boardgames_tile.flash_request_sent');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_event_details', ['id' => $eventId]);
    }

    #[Route('/{id}/accept', name: 'app_plugin_boardgames_request_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(int $id, Request $request): Response
    {
        $bringRequest = $this->requireOwnedRequest($id, $request, 'app_plugin_boardgames_request_accept');

        try {
            $this->requestService->accept($bringRequest);
            $this->addFlash('success', 'boardgames_tile.flash_request_accepted');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_event_details', ['id' => $bringRequest->getEvent()?->getId()]);
    }

    #[Route('/{id}/decline', name: 'app_plugin_boardgames_request_decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decline(int $id, Request $request): Response
    {
        $bringRequest = $this->requireOwnedRequest($id, $request, 'app_plugin_boardgames_request_decline');

        try {
            $this->requestService->decline($bringRequest);
            $this->addFlash('success', 'boardgames_tile.flash_request_declined');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_event_details', ['id' => $bringRequest->getEvent()?->getId()]);
    }

    #[Route('/{id}/withdraw', name: 'app_plugin_boardgames_request_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_request_withdraw' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $bringRequest = $this->requestService->find($id);
        if ($bringRequest === null || $bringRequest->getRequestedBy()?->getId() !== $this->getAuthedUser()->getId()) {
            throw $this->createNotFoundException('Request not found');
        }

        try {
            $this->requestService->withdraw($bringRequest);
            $this->addFlash('success', 'boardgames_tile.flash_request_withdrawn');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_event_details', ['id' => $bringRequest->getEvent()?->getId()]);
    }

    private function requireEvent(int $eventId): Event
    {
        $event = $this->eventRepository->find($eventId);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Event not found');
        }

        return $event;
    }

    private function requireOwnedRequest(int $id, Request $request, string $csrfId): BringRequest
    {
        if (!$this->isCsrfTokenValid($csrfId . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $bringRequest = $this->requestService->find($id);
        if ($bringRequest === null || $bringRequest->getOwnerUser()?->getId() !== $this->getAuthedUser()->getId()) {
            throw $this->createNotFoundException('Request not found');
        }

        return $bringRequest;
    }
}
