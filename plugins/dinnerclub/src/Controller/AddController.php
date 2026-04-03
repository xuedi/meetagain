<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Controller\AbstractController;
use Plugin\Dinnerclub\Form\DishAddType;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub')]
final class AddController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
    ) {}

    #[Route('/add', name: 'plugin_dinnerclub_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(DishAddType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_ORGANIZER');

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

            $this->addFlash('success', $isManager ? 'Dish has been added.' : 'Dish has been submitted for approval.');

            return $this->redirectToRoute('app_plugin_dinnerclub');
        }

        return $this->render('@Dinnerclub/add.html.twig', [
            'form' => $form,
            'currentLocale' => $request->getLocale(),
        ]);
    }
}
