<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Controller\AbstractController;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;
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
        private readonly DishImageSuggestionRepository $imageSuggestionRepo,
    ) {}

    #[Route('', name: 'plugin_dinnerclub_suggestions_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Dinnerclub/suggestion/list.html.twig', [
            'dishes' => $this->dishService->getDishesWithSuggestions(),
            'imageSuggestionCount' => $this->imageSuggestionRepo->count([]),
        ]);
    }

    #[Route('/image', name: 'plugin_dinnerclub_image_suggestions_list', methods: ['GET'])]
    public function imageList(): Response
    {
        return $this->render('@Dinnerclub/suggestion/image_list.html.twig', [
            'dishes' => $this->dishService->getDishesWithImageSuggestions(),
        ]);
    }

    #[Route('/image/{id}', name: 'plugin_dinnerclub_image_suggestion_view', methods: ['GET'])]
    public function imageView(int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        return $this->render('@Dinnerclub/suggestion/image_view.html.twig', [
            'dish' => $dish,
            'suggestions' => $this->dishService->getImageSuggestionsForDish($dish),
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
}
