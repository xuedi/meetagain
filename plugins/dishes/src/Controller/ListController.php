<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use App\Controller\AbstractController;
use Plugin\Dishes\Entity\DishList;
use Plugin\Dishes\Form\DishListType;
use Plugin\Dishes\Repository\DishRepository;
use Plugin\Dishes\Service\DishListService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dishes/lists')]
#[IsGranted('ROLE_USER')]
class ListController extends AbstractController
{
    public function __construct(
        private readonly DishListService $listService,
        private readonly DishRepository $dishRepo,
    ) {
    }

    #[Route('', name: 'plugin_dishes_lists', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getAuthedUser();

        return $this->render('@Dishes/lists/index.html.twig', [
            'myLists' => $this->listService->getUserLists($user->getId()),
            'publicLists' => $this->listService->getPublicListsByOthers($user->getId()),
        ]);
    }

    #[Route('/create', name: 'plugin_dishes_lists_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $form = $this->createForm(DishListType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $data = $form->getData();

            $this->listService->createList(
                userId: $user->getId(),
                name: $data->getName(),
                description: $data->getDescription(),
                isPublic: $data->isPublic(),
            );

            $this->addFlash('success', 'List has been created.');

            return $this->redirectToRoute('plugin_dishes_lists');
        }

        return $this->render('@Dishes/lists/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: 'plugin_dishes_lists_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $list = $this->listService->getList($id);
        if ($list === null) {
            throw $this->createNotFoundException('List not found');
        }

        $user = $this->getAuthedUser();
        if ($list->getCreatedBy() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own lists');
        }

        $form = $this->createForm(DishListType::class, $list);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->listService->updateList(
                listId: $id,
                userId: $user->getId(),
                name: $list->getName(),
                description: $list->getDescription(),
                isPublic: $list->isPublic(),
            );

            $this->addFlash('success', 'List has been updated.');

            return $this->redirectToRoute('plugin_dishes_lists_view', ['id' => $id]);
        }

        return $this->render('@Dishes/lists/edit.html.twig', [
            'form' => $form,
            'list' => $list,
        ]);
    }

    #[Route('/view/{id}', name: 'plugin_dishes_lists_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $list = $this->listService->getList($id);
        if ($list === null) {
            throw $this->createNotFoundException('List not found');
        }

        $user = $this->getAuthedUser();
        if (!$list->isPublic() && $list->getCreatedBy() !== $user->getId()) {
            throw $this->createAccessDeniedException('This list is private');
        }

        $dishes = $this->dishRepo->findByIds($list->getDishIds());

        return $this->render('@Dishes/lists/view.html.twig', [
            'list' => $list,
            'dishes' => $dishes,
            'isOwner' => $list->getCreatedBy() === $user->getId(),
        ]);
    }

    #[Route('/delete/{id}', name: 'plugin_dishes_lists_delete', methods: ['GET'])]
    public function delete(int $id): Response
    {
        $user = $this->getAuthedUser();

        try {
            $this->listService->deleteList($id, $user->getId());
            $this->addFlash('success', 'List has been deleted.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('plugin_dishes_lists');
    }

    #[Route('/{listId}/add/{dishId}', name: 'plugin_dishes_lists_add_dish', methods: ['POST'])]
    public function addDish(int $listId, int $dishId): JsonResponse
    {
        $user = $this->getAuthedUser();

        try {
            $this->listService->addDishToList($listId, $dishId, $user->getId());

            return new JsonResponse(['success' => true, 'message' => 'Dish added to list']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/{listId}/remove/{dishId}', name: 'plugin_dishes_lists_remove_dish', methods: ['POST'])]
    public function removeDish(int $listId, int $dishId): JsonResponse
    {
        $user = $this->getAuthedUser();

        try {
            $this->listService->removeDishFromList($listId, $dishId, $user->getId());

            return new JsonResponse(['success' => true, 'message' => 'Dish removed from list']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
