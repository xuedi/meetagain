<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Item\Tag\AssignmentFormHelper;
use Module\Proposal\Contract\Change\ChangeInterface;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/glossary/edit')]
#[IsGranted('ROLE_ORGANIZER')]
final class EditController extends AbstractGlossaryController
{
    public function __construct(
        GlossaryService $service,
        private readonly ChangeInterface $changeProposalService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {
        parent::__construct($service);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $entry = $this->service->getManaged($id);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        $draft = $this->service->draftOf($entry);
        $form = $this->createForm(GlossaryType::class, $draft, ['entry_id' => $id]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->update($draft, $id, $this->assignmentFormHelper->extractAssignment($form));

            return $this->redirectToRoute('app_plugin_glossary');
        }

        $pendingProposals = [];
        foreach ($this->changeProposalService->pendingFor(GlossaryTaggableTypeProvider::ITEM_TYPE, $id) as $proposal) {
            $pendingProposals[] = [
                'proposal' => $proposal,
                'rows' => $proposal->rows,
            ];
        }

        return $this->renderPage('@Glossary/edit.html.twig', [
            'editItem' => $entry,
            'pendingProposals' => $pendingProposals,
            'form' => $form,
        ]);
    }
}
