<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Controller\AbstractController;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub/approval')]
#[IsGranted('ROLE_ORGANIZER')]
final class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
    ) {}

    #[Route('/pending', name: 'plugin_dinnerclub_approval_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Dinnerclub/approval/pending.html.twig', [
            'dishes' => $this->dishService->getPendingDishes(),
        ]);
    }

    #[Route('/view/{id}', name: 'plugin_dinnerclub_approval_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        return $this->render('@Dinnerclub/approval/view.html.twig', [
            'dish' => $dish,
        ]);
    }
}
