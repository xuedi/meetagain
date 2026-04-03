<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Dinnerclub\Activity\Messages\DishApproved;
use Plugin\Dinnerclub\Activity\Messages\DishRejected;
use Plugin\Dinnerclub\Entity\Dish;
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
        private readonly ActivityService $activityService,
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

    #[Route('/approve/{id}', name: 'plugin_dinnerclub_approval_approve', methods: ['GET'])]
    public function approve(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        $this->dishService->approveDish($id);
        $this->addFlash('success', 'Dish has been approved.');

        if ($dish !== null) {
            $this->activityService->log(DishApproved::TYPE, $this->getAuthedUser(), [
                'dish_id' => $id,
                'dish_name' => $this->getDishName($dish),
            ]);
        }

        return $this->redirectToRoute('plugin_dinnerclub_approval_pending');
    }

    #[Route('/reject/{id}', name: 'plugin_dinnerclub_approval_reject', methods: ['GET'])]
    public function reject(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        $this->dishService->rejectDish($id);
        $this->addFlash('info', 'Dish has been rejected and deleted.');

        if ($dish !== null) {
            $this->activityService->log(DishRejected::TYPE, $this->getAuthedUser(), [
                'dish_id' => $id,
                'dish_name' => $this->getDishName($dish),
            ]);
        }

        return $this->redirectToRoute('plugin_dinnerclub_approval_pending');
    }

    private function getDishName(Dish $dish): string
    {
        $originLang = $dish->getOriginLang();
        if ($originLang !== null) {
            $translation = $dish->findTranslation($originLang);
            if ($translation !== null) {
                return $translation->getName();
            }
        }

        $first = $dish->getTranslations()->first();

        return $first !== false ? $first->getName() : '[unknown]';
    }
}
