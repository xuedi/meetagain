<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Activity\ActivityService;
use App\Entity\User;
use Plugin\Glossary\Activity\Messages\EntryCreated;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/glossary')]
#[IsGranted('ROLE_USER')]
final class NewController extends AbstractGlossaryController
{
    public function __construct(
        GlossaryService $service,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct($service);
    }

    #[Route('/new', name: 'app_plugin_glossary_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $glossary = new Glossary();

        $form = $this->createForm(GlossaryType::class, $glossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $categoryId = $form->has('category') ? $this->intOrNull($form->get('category')->getData()) : null;
            $this->service->create($glossary, $this->getAuthedUser()->getId(), $this->isGranted('ROLE_ORGANIZER'), $categoryId);

            $this->activityService->log(EntryCreated::TYPE, $this->getUser(), [
                'glossary_id' => $glossary->getId(),
                'term' => $glossary->getPhrase(),
            ]);

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->renderPage('@Glossary/new.html.twig', [
            'form' => $form,
        ]);
    }
}
