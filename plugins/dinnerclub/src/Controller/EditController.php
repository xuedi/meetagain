<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Enum\ImageType;
use App\Service\Config\LanguageService;
use App\Service\Media\ImageService;
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
final class EditController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
        private readonly LanguageService $languageService,
        private readonly ImageService $imageService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/translate/{id}/{lang}', name: 'plugin_dinnerclub_translate', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function translate(Request $request, int $id, ?string $lang = null): Response
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
                'phonetic' => $existingTranslation?->getPhonetic() ?? '',
                'description' => $existingTranslation?->getDescription() ?? '',
                'recipe' => $existingTranslation?->getRecipe() ?? '',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_ORGANIZER');

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

            return $this->redirectToRoute('plugin_dinnerclub_item_show', ['id' => $id]);
        }

        return $this->render('@Dinnerclub/translate.html.twig', [
            'dish' => $dish,
            'form' => $form,
            'targetLanguage' => $lang,
            'availableLanguages' => $this->languageService->getFilteredEnabledCodes(),
            'existingTranslation' => $existingTranslation,
        ]);
    }

    #[Route('/edit/{id}', name: 'plugin_dinnerclub_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function edit(Request $request, int $id): Response
    {
        $dish = $this->dishService->getDish($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $form = $this->createForm(DishEditType::class, $dish);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('previewImage')->getData();
            if ($uploadedFile !== null) {
                $image = $this->imageService->upload($uploadedFile, $this->getAuthedUser(), ImageType::PluginDishPreview);
                $this->imageService->createThumbnails($image, ImageType::PluginDishPreview);
                $dish->setPreviewImage($image);
            }

            $this->dishService->saveBaseData($dish);
            $this->addFlash('success', 'Dish has been updated.');

            return $this->redirectToRoute('plugin_dinnerclub_item_show', ['id' => $id]);
        }

        return $this->render('@Dinnerclub/edit.html.twig', [
            'form' => $form,
            'dish' => $dish,
        ]);
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
