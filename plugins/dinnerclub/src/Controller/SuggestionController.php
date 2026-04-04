<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Dinnerclub\Activity\Messages\ImageSuggestionApproved;
use Plugin\Dinnerclub\Activity\Messages\ImageSuggestionRejected;
use Plugin\Dinnerclub\Activity\Messages\SuggestionApproved;
use Plugin\Dinnerclub\Activity\Messages\SuggestionRejected;
use Plugin\Dinnerclub\Entity\Dish;
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
        private readonly ActivityService $activityService,
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

    #[Route('/image/apply/{suggestionId}', name: 'plugin_dinnerclub_image_suggestion_apply', methods: ['GET'])]
    public function imageApply(int $suggestionId): Response
    {
        $suggestion = $this->imageSuggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw $this->createNotFoundException('Image suggestion not found');
        }

        $dish = $suggestion->getDish();
        $dishId = $dish?->getId();

        $leftOver = $this->dishService->applyImageSuggestion($suggestionId);

        $this->addFlash('success', 'Image suggestion has been applied.');

        if ($dish !== null) {
            $this->activityService->log(ImageSuggestionApproved::TYPE, $this->getAuthedUser(), [
                'dish_id' => $dishId,
                'dish_name' => $this->getDishName($dish),
                'suggestion_type' => $suggestion->getType()?->value,
            ]);
        }

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_image_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_image_suggestion_view', ['id' => $dishId]);
    }

    #[Route('/image/deny/{suggestionId}', name: 'plugin_dinnerclub_image_suggestion_deny', methods: ['GET'])]
    public function imageDeny(int $suggestionId): Response
    {
        $suggestion = $this->imageSuggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw $this->createNotFoundException('Image suggestion not found');
        }

        $dish = $suggestion->getDish();
        $dishId = $dish?->getId();

        $leftOver = $this->dishService->denyImageSuggestion($suggestionId);

        $this->addFlash('info', 'Image suggestion has been rejected.');

        if ($dish !== null) {
            $this->activityService->log(ImageSuggestionRejected::TYPE, $this->getAuthedUser(), [
                'dish_id' => $dishId,
                'dish_name' => $this->getDishName($dish),
                'suggestion_type' => $suggestion->getType()?->value,
            ]);
        }

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_image_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_image_suggestion_view', ['id' => $dishId]);
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
        $dish = $this->dishService->getDish($id);
        $leftOver = $this->dishService->applySuggestion($id, $hash);

        $this->addFlash('success', 'Suggestion has been applied.');

        if ($dish !== null) {
            $this->activityService->log(SuggestionApproved::TYPE, $this->getAuthedUser(), [
                'dish_id' => $id,
                'dish_name' => $this->getDishName($dish),
            ]);
        }

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_suggestion_view', ['id' => $id]);
    }

    #[Route('/deny/{id}/{hash}', name: 'plugin_dinnerclub_suggestion_deny', methods: ['GET'])]
    public function deny(int $id, string $hash): Response
    {
        $dish = $this->dishService->getDish($id);
        $leftOver = $this->dishService->denySuggestion($id, $hash);

        $this->addFlash('info', 'Suggestion has been rejected.');

        if ($dish !== null) {
            $this->activityService->log(SuggestionRejected::TYPE, $this->getAuthedUser(), [
                'dish_id' => $id,
                'dish_name' => $this->getDishName($dish),
            ]);
        }

        if ($leftOver === 0) {
            return $this->redirectToRoute('plugin_dinnerclub_suggestions_list');
        }

        return $this->redirectToRoute('plugin_dinnerclub_suggestion_view', ['id' => $id]);
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
