<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Controller\AbstractController;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub/suggestion')]
#[IsGranted('ROLE_ORGANIZER')]
final class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
    ) {}

    #[Route('', name: 'plugin_dinnerclub_suggestions_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Dinnerclub/suggestion/list.html.twig', [
            'dishes' => $this->dishService->getDishesWithSuggestions(),
        ]);
    }

    #[Route('/view/{id}', name: 'plugin_dinnerclub_suggestion_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        return $this->render('@Dinnerclub/suggestion/view.html.twig', [
            'dish' => $dish,
            'suggestions' => $dish->getSuggestionObjects(),
        ]);
    }

    #[Route('/apply/{id}/{hash}', name: 'plugin_dinnerclub_suggestion_apply', methods: ['GET'])]
    public function apply(int $id, string $hash): Response
    {
        $leftOver = $this->dishService->applySuggestion($id, $hash);

        $this->addFlash('success', 'Suggestion has been applied.');

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_suggestion_view', ['id' => $id]);
    }

    #[Route('/deny/{id}/{hash}', name: 'plugin_dinnerclub_suggestion_deny', methods: ['GET'])]
    public function deny(int $id, string $hash): Response
    {
        $leftOver = $this->dishService->denySuggestion($id, $hash);

        $this->addFlash('info', 'Suggestion has been rejected.');

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_suggestion_view', ['id' => $id]);
    }
}
