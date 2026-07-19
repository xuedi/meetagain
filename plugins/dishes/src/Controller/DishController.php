<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Dishes\Activity\Messages\DishAdded;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Form\DishAddType;
use Plugin\Dishes\Form\DishEditType;
use Plugin\Dishes\Service\ConfigService;
use Plugin\Dishes\Service\DishImageService;
use Plugin\Dishes\Service\DishService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dishes')]
final class DishController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
        private readonly DishImageService $dishImageService,
        private readonly ActivityService $activityService,
        private readonly ConfigService $configService,
    ) {}

    #[Route('', name: 'app_dishes_dishlist', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $itemIds = array_map(static fn(Dish $dish): int => (int) $dish->getId(), $this->dishService->getList());

        return $this->render('@Dishes/dish/list.html.twig', [
            'itemIds' => $itemIds,
            'footer' => $this->configService->getConfig()->getFooterFor($request->getLocale()),
        ]);
    }

    #[Route('/add', name: 'app_plugin_dishes_dish_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(DishAddType::class, null, ['current_locale' => $request->getLocale()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $dish = $this->dishService->create(
                    name: $form->get('name')->getData(),
                    language: $form->get('language')->getData(),
                    description: $form->get('description')->getData(),
                    recipe: $form->get('recipe')->getData(),
                    phonetic: $form->get('phonetic')->getData(),
                    origin: $form->get('origin')->getData(),
                    userId: $user->getId(),
                );

                $previewFile = $form->get('previewFile')->getData();
                if ($previewFile !== null) {
                    $image = $this->dishImageService->uploadFromFile($previewFile, $user->getId());
                    if ($image !== null) {
                        $this->dishService->addGalleryImage($dish, $image);
                    }
                }

                $this->activityService->log(DishAdded::TYPE, $user, [
                    'dish_id' => $dish->getId(),
                    'dish_name' => $dish->getAnyTranslatedName(),
                ]);
                $this->addFlash('success', 'dishes_dish.flash_added');

                return $this->redirectToRoute('app_plugin_dishes_dish_show', ['id' => $dish->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Dishes/dish/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_dishes_dish_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $dish = $this->dishService->get($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $liked = $this->isGranted('ROLE_USER') && $this->dishService->isLikedByUser($dish, $this->getAuthedUser()->getId());

        return $this->render('@Dishes/dish/detail.html.twig', [
            'dish' => $dish,
            'liked' => $liked,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_dishes_dish_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
    public function edit(int $id, Request $request): Response
    {
        $dish = $this->dishService->get($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $locale = $request->getLocale();
        $translation = $dish->findTranslation($locale);
        $form = $this->createForm(DishEditType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $form->get('name')->setData($translation?->getName() ?? $dish->getAnyTranslatedName());
            $form->get('description')->setData($translation?->getDescription());
            $form->get('recipe')->setData($translation?->getRecipe());
            $form->get('phonetic')->setData($dish->getPhonetic());
            $form->get('origin')->setData($dish->getOrigin());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            try {
                $dish->setPhonetic($form->get('phonetic')->getData());
                $dish->setOrigin($form->get('origin')->getData());
                $this->dishService->updateTranslation(
                    $dish,
                    $locale,
                    $form->get('name')->getData(),
                    $form->get('description')->getData(),
                    $form->get('recipe')->getData(),
                );
                $this->dishService->saveBaseData($dish);

                $previewFile = $form->get('previewFile')->getData();
                if ($previewFile !== null) {
                    $image = $this->dishImageService->uploadFromFile($previewFile, $user->getId());
                    if ($image !== null) {
                        $this->dishService->addGalleryImage($dish, $image);
                    }
                }

                $this->addFlash('success', 'dishes_dish.flash_updated');

                return $this->redirectToRoute('app_plugin_dishes_dish_show', ['id' => $dish->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Dishes/dish/edit.html.twig', [
            'form' => $form,
            'dish' => $dish,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_dishes_dish_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
    public function delete(int $id, Request $request): Response
    {
        $dish = $this->dishService->get($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        if (!$this->isCsrfTokenValid('app_plugin_dishes_dish_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->dishService->delete($dish);
        $this->addFlash('success', 'dishes_dish.flash_deleted');

        return $this->redirectToRoute('app_dishes_dishlist');
    }

    #[Route('/{id}/like', name: 'app_plugin_dishes_dish_like', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function like(int $id, Request $request): Response
    {
        $dish = $this->dishService->get($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        if (!$this->isCsrfTokenValid('dishes_like' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->dishService->toggleLike($dish, $this->getAuthedUser()->getId());

        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_plugin_dishes_dish_show', ['id' => $id]);
    }
}
