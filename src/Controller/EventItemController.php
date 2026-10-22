<?php declare(strict_types=1);

namespace App\Controller;

use App\Item\AssociationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/event/{id}/item')]
final class EventItemController extends AbstractController
{
    public function __construct(
        private readonly AssociationService $itemAssociationService,
    ) {}

    #[Route('/attach', name: 'app_item_attach', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function attachItem(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('app_item_attach' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $itemType = (string) $request->request->get('item_type');
        $itemId = $request->request->getInt('item_id');
        if ($itemType === '' || $itemId <= 0) {
            throw new BadRequestHttpException('Missing item type or id.');
        }

        $this->itemAssociationService->attach($id, $itemType, $itemId, (int) $this->getAuthedUser()->getId());

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }

    #[Route('/detach', name: 'app_item_detach', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function detachItem(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('app_item_detach' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $itemType = (string) $request->request->get('item_type');
        $itemId = $request->request->getInt('item_id');
        if ($itemType === '' || $itemId <= 0) {
            throw new BadRequestHttpException('Missing item type or id.');
        }

        $this->itemAssociationService->detach($id, $itemType, $itemId);

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }
}
