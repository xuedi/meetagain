<?php declare(strict_types=1);

namespace Plugin\Wishlist\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\ItemTypeRegistry;
use App\Repository\EventRepository;
use Plugin\Wishlist\Activity\Messages\WishlistAdded;
use Plugin\Wishlist\Service\WishlistService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/wishlist')]
#[IsGranted('ROLE_USER')]
final class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly ItemTypeRegistry $registry,
        private readonly EventRepository $eventRepo,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/mine', name: 'app_wishlist_mine', methods: ['GET'])]
    public function mine(): Response
    {
        $entries = $this->wishlistService->listForUser($this->getAuthedUser()->getId());

        $waitingSince = [];
        foreach ($entries as $entry) {
            $createdAt = $entry->getCreatedAt();
            $waitingSince[$entry->getId()] = $createdAt === null ? 0 : $this->wishlistService->countPastEventsInGroupSince($createdAt);
        }

        return $this->render('@Wishlist/wishlist/mine.html.twig', [
            'entries' => $entries,
            'waitingSince' => $waitingSince,
        ]);
    }

    #[Route('/group', name: 'app_wishlist_group', methods: ['GET'])]
    public function group(): Response
    {
        return $this->render('@Wishlist/wishlist/group.html.twig', [
            'byMember' => $this->wishlistService->groupByMember(),
        ]);
    }

    #[Route('/pick/{eventId}/{itemType}', name: 'app_wishlist_pick', methods: ['GET'], requirements: ['eventId' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function pick(int $eventId, string $itemType): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null || !$this->registry->has($itemType)) {
            throw $this->createNotFoundException('Event or item type not found');
        }

        return $this->render('@Wishlist/wishlist/pick.html.twig', [
            'event' => $event,
            'itemType' => $itemType,
            'candidates' => $this->wishlistService->aggregateByItem($itemType),
        ]);
    }

    #[Route('/add/{itemType}/{itemId}', name: 'app_wishlist_add', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function add(string $itemType, int $itemId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wishlist_add' . $itemType . $itemId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if (!$this->registry->has($itemType)) {
            throw $this->createNotFoundException('Unknown item type');
        }

        $user = $this->getAuthedUser();
        $this->wishlistService->add($itemType, $itemId, $user->getId());
        $this->activityService->log(WishlistAdded::TYPE, $user, [
            'item_type' => $itemType,
            'item_id' => $itemId,
        ]);
        $this->addFlash('success', 'wishlist_entry.flash_added');

        return $this->backTo($request);
    }

    #[Route('/remove/{itemType}/{itemId}', name: 'app_wishlist_remove', methods: ['POST'], requirements: ['itemId' => '\d+'])]
    public function remove(string $itemType, int $itemId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wishlist_remove' . $itemType . $itemId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->wishlistService->remove($itemType, $itemId, $this->getAuthedUser()->getId());
        $this->addFlash('success', 'wishlist_entry.flash_removed');

        return $this->backTo($request);
    }

    private function backTo(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_wishlist_mine');
    }
}
