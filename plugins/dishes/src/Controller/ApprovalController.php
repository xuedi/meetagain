<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use App\Controller\AbstractController;
use Plugin\Dishes\Service\DishService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dishes/approval')]
#[IsGranted('ROLE_MANAGER')]
class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
    ) {
    }

    #[Route('/pending', name: 'plugin_dishes_approval_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Dishes/approval/pending.html.twig', [
            'dishes' => $this->dishService->getPendingDishes(),
        ]);
    }

    #[Route('/view/{id}', name: 'plugin_dishes_approval_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        return $this->render('@Dishes/approval/view.html.twig', [
            'dish' => $dish,
        ]);
    }

    #[Route('/approve/{id}', name: 'plugin_dishes_approval_approve', methods: ['GET'])]
    public function approve(int $id): Response
    {
        $this->dishService->approveDish($id);
        $this->addFlash('success', 'Dish has been approved.');

        return $this->redirectToRoute('plugin_dishes_approval_pending');
    }

    #[Route('/reject/{id}', name: 'plugin_dishes_approval_reject', methods: ['GET'])]
    public function reject(int $id): Response
    {
        $this->dishService->rejectDish($id);
        $this->addFlash('info', 'Dish has been rejected and deleted.');

        return $this->redirectToRoute('plugin_dishes_approval_pending');
    }
}
