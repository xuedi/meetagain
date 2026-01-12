<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use App\Controller\AbstractController;
use App\Service\TranslationService;
use Plugin\Dishes\Form\DishTranslationType;
use Plugin\Dishes\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dishes')]
class EditController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
        private readonly TranslationService $translationService,
    ) {
    }

    #[Route('/translate/{id}/{lang}', name: 'plugin_dishes_translate', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function translate(Request $request, int $id, ?string $lang = null): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $lang = $lang ?? $request->getLocale();
        $existingTranslation = $dish->findTranslation($lang);

        $form = $this->createForm(DishTranslationType::class, null, [
            'data' => [
                'name' => $existingTranslation?->getName() ?? '',
                'phonetic' => $existingTranslation?->getPhonetic() ?? '',
                'description' => $existingTranslation?->getDescription() ?? '',
                'recipe' => $existingTranslation?->getRecipe() ?? '',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_MANAGER');

            $this->dishService->updateTranslation(
                dishId: $id,
                language: $lang,
                userId: $user->getId(),
                isManager: $isManager,
                name: $form->get('name')->getData(),
                phonetic: $form->get('phonetic')->getData(),
                description: $form->get('description')->getData(),
                recipe: $form->get('recipe')->getData(),
            );

            $this->addFlash('success', $isManager
                ? 'Translation has been updated.'
                : 'Translation suggestion has been submitted for review.');

            return $this->redirectToRoute('plugin_dishes_item_show', ['id' => $id]);
        }

        return $this->render('@Dishes/translate.html.twig', [
            'dish' => $dish,
            'form' => $form,
            'targetLanguage' => $lang,
            'availableLanguages' => $this->translationService->getLanguageCodes(),
            'existingTranslation' => $existingTranslation,
        ]);
    }

    #[Route('/edit/{id}/origin', name: 'plugin_dishes_edit_origin', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function editOrigin(Request $request, int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $origin = $request->request->get('origin');
        $user = $this->getAuthedUser();
        $isManager = $this->isGranted('ROLE_MANAGER');

        $this->dishService->updateOrigin($id, $user->getId(), $isManager, $origin);

        $this->addFlash('success', $isManager
            ? 'Origin has been updated.'
            : 'Origin suggestion has been submitted for review.');

        return $this->redirectToRoute('plugin_dishes_item_show', ['id' => $id]);
    }
}
