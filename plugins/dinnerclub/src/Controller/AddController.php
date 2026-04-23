<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Dinnerclub\Activity\Messages\DishCreated;
use Plugin\Dinnerclub\Form\DishAddType;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dinnerclub')]
#[IsGranted('ROLE_USER')]
final class AddController extends AbstractController
{
    public function __construct(
        private readonly DishService $dishService,
        private readonly ActivityService $activityService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/add', name: 'plugin_dinnerclub_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $form = $this->createForm(DishAddType::class, null, ['current_locale' => $request->getLocale()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_ORGANIZER');
            $dishName = $form->get('name')->getData();

            $dish = $this->dishService->createDish(
                name: $dishName,
                language: $form->get('language')->getData(),
                userId: $user->getId(),
                isManager: $isManager,
                phonetic: $form->get('phonetic')->getData(),
                description: $form->get('description')->getData(),
                recipe: $form->get('recipe')->getData(),
                origin: $form->get('origin')->getData(),
            );

            $this->activityService->log(DishCreated::TYPE, $user, [
                'dish_id' => $dish->getId(),
                'dish_name' => $dishName,
            ]);

            $this->addFlash('success', $this->translator->trans($isManager ? 'dinnerclub.flash_added' : 'dinnerclub.flash_submitted'));

            return $this->redirectToRoute('app_plugin_dinnerclub');
        }

        return $this->render('@Dinnerclub/add.html.twig', [
            'form' => $form,
        ]);
    }
}
