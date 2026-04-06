<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Service\Config\LanguageService;
use Plugin\Dinnerclub\Activity\Messages\SuggestionCreated;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Form\DishEditType;
use Plugin\Dinnerclub\Form\DishTranslationType;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub')]
#[IsGranted('ROLE_USER')]
final class EditController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
        private readonly LanguageService $languageService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/edit/{id}/{lang}', name: 'plugin_dinnerclub_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ?string $lang = null): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $lang ??= $request->getLocale();
        $existingTranslation = $dish->findTranslation($lang);

        $form = $this->createForm(DishTranslationType::class, null, [
            'data' => [
                'name' => $existingTranslation?->getName() ?? '',
                'description' => $existingTranslation?->getDescription() ?? '',
                'recipe' => $existingTranslation?->getRecipe() ?? '',
            ],
        ]);

        $originForm = $this->createForm(DishEditType::class, $dish);

        $form->handleRequest($request);
        $originForm->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_ORGANIZER');

            $this->dishService->updateTranslation(
                dishId: $id,
                language: $lang,
                userId: $user->getId(),
                isManager: $isManager,
                name: $form->get('name')->getData(),
                description: $form->get('description')->getData(),
                recipe: $form->get('recipe')->getData(),
            );

            if (!$isManager) {
                $this->activityService->log(SuggestionCreated::TYPE, $user, [
                    'dish_id' => $id,
                    'dish_name' => $this->getDishName($dish),
                    'field' => $lang,
                ]);
            }

            $this->addFlash(
                'success',
                $isManager ? 'Translation has been updated.' : 'Translation suggestion has been submitted for review.',
            );

            return $this->redirectToRoute('plugin_dinnerclub_edit', ['id' => $id, 'lang' => $lang]);
        }

        if ($originForm->isSubmitted() && $originForm->isValid()) {
            $this->dishService->saveBaseData($dish);
            $this->addFlash('success', 'Dish origin has been updated.');

            return $this->redirectToRoute('plugin_dinnerclub_edit', ['id' => $id, 'lang' => $lang]);
        }

        return $this->render('@Dinnerclub/edit.html.twig', [
            'dish' => $dish,
            'form' => $form,
            'originForm' => $originForm,
            'targetLanguage' => $lang,
            'availableLanguages' => $this->languageService->getFilteredEnabledCodes(),
            'existingTranslation' => $existingTranslation,
            'galleryImages' => $dish->getVisibleGalleryImages(),
            'isOrganizer' => $this->isGranted('ROLE_ORGANIZER'),
        ]);
    }

    private function getDishName(Dish $dish): string
    {
        return $dish->getAnyTranslatedName() ?: '[unknown]';
    }
}
