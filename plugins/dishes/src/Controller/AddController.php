<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use App\Controller\AbstractController;
use Plugin\Dishes\Form\DishAddType;
use Plugin\Dishes\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dishes')]
class AddController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
    ) {
    }

    #[Route('/add', name: 'plugin_dishes_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(DishAddType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_MANAGER');

            $this->dishService->createDish(
                name: $form->get('name')->getData(),
                language: $request->getLocale(),
                userId: $user->getId(),
                isManager: $isManager,
                phonetic: $form->get('phonetic')->getData(),
                description: $form->get('description')->getData(),
                recipe: $form->get('recipe')->getData(),
                origin: $form->get('origin')->getData(),
            );

            $this->addFlash('success', $isManager
                ? 'Dish has been added.'
                : 'Dish has been submitted for approval.');

            return $this->redirectToRoute('app_plugin_dishes');
        }

        return $this->render('@Dishes/add.html.twig', [
            'form' => $form,
            'currentLocale' => $request->getLocale(),
        ]);
    }
}
