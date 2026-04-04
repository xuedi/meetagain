<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Dinnerclub\Activity\Messages\DishLiked;
use Plugin\Dinnerclub\Activity\Messages\DishUnliked;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\ViewType;
use Plugin\Dinnerclub\Repository\DishRepository;
use Plugin\Dinnerclub\Service\DishListService;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub')]
final class IndexController extends AbstractController
{
    public function __construct(
        private readonly DishRepository $repo,
        private readonly DishService $dishService,
        private readonly DishListService $listService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('', name: 'app_plugin_dinnerclub', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $isManager = $this->isGranted('ROLE_ORGANIZER');

        $dishes = $isManager ? $this->repo->findAll() : $this->dishService->getApprovedDishes();

        $userLists = [];
        $favoriteDishIds = [];
        if ($this->isGranted('ROLE_USER')) {
            $userId = $this->getAuthedUser()->getId();
            $userLists = $this->listService->getUserLists($userId);
            $favoriteDishIds = $this->dishService->getLikedDishIds($userId);
        }

        return $this->render('@Dinnerclub/index.html.twig', [
            'list' => $dishes,
            'viewType' => $session->get('dishesViewType', ViewType::Tiles->value),
            'viewTypeList' => [
                ViewType::List->value => 'list',
                ViewType::Tiles->value => 'grip',
                ViewType::Grid->value => 'table-cells',
                ViewType::Gallery->value => 'images',
            ],
            'pendingCount' => $isManager ? count($this->dishService->getPendingDishes()) : 0,
            'suggestionCount' => $isManager ? count($this->dishService->getDishesWithSuggestions()) : 0,
            'userLists' => $userLists,
            'favoriteDishIds' => $favoriteDishIds,
        ]);
    }

    #[Route('/view/{id}', name: 'plugin_dinnerclub_item_show', methods: ['GET'])]
    public function view(int $id): Response
    {
        $dish = $this->repo->findOneBy(['id' => $id]);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $userLists = [];
        $userLiked = false;
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getAuthedUser();
            $userLists = $this->listService->getUserLists($user->getId());
            $userLiked = $this->dishService->isLikedByUser($dish->getId(), $user->getId());
        }

        $isOrganizer = $this->isGranted('ROLE_ORGANIZER');

        return $this->render('@Dinnerclub/details.html.twig', [
            'dish' => $dish,
            'userLists' => $userLists,
            'userLiked' => $userLiked,
            'galleryImages' => $dish->getVisibleGalleryImages(),
            'isOrganizer' => $isOrganizer,
            'imageSuggestionCount' => $isOrganizer ? $this->dishService->countImageSuggestions($dish) : 0,
        ]);
    }

    #[Route('/like/{id}', name: 'plugin_dinnerclub_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(int $id): JsonResponse
    {
        $dish = $this->dishService->getDish($id);
        $user = $this->getAuthedUser();
        $liked = $this->dishService->toggleLike($id, $user->getId());

        $type = $liked ? DishLiked::TYPE : DishUnliked::TYPE;
        $this->activityService->log($type, $user, [
            'dish_id' => $id,
            'dish_name' => $dish !== null ? $this->getDishName($dish) : '[unknown]',
        ]);

        return new JsonResponse(['liked' => $liked]);
    }

    #[Route('/filter/{name}/set/{value}', name: 'plugin_dinnerclub_filter', methods: ['GET'])]
    public function filter(ViewType $view): Response
    {
        // save settings to session and display
        return $this->redirectToRoute('app_plugin_dinnerclub');
    }

    #[Route('/set/view/{type}', name: 'plugin_dinnerclub_set_view_type', methods: ['GET'])]
    public function setViewType(Request $request, ViewType $type): Response
    {
        $session = $request->getSession();
        $session->set('dishesViewType', $type->value);

        return $this->redirectToRoute('app_plugin_dinnerclub');
    }

    private function getDishName(Dish $dish): string
    {
        return $dish->getAnyTranslatedName() ?: '[unknown]';
    }
}
