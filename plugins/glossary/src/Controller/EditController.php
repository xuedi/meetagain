<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use App\Item\Tag\AssignmentFormHelper;
use App\Review\ChangeProposalService;
use App\Review\FieldChange;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/glossary/edit')]
#[IsGranted('ROLE_USER')]
final class EditController extends AbstractGlossaryController
{
    public function __construct(
        GlossaryService $service,
        private readonly ChangeProposalService $changeProposalService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {
        parent::__construct($service);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $newGlossary = $this->service->getManaged($id);
        if ($newGlossary === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(GlossaryType::class, $newGlossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->detach($newGlossary); // detach first: a managed entity would be flushed with the request's changes
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }

            $tagIds = $this->assignmentFormHelper->extractAssignment($form);

            if ($this->isGranted('ROLE_ORGANIZER')) {
                $this->service->update($newGlossary, $id, $tagIds);
            } else {
                $this->changeProposalService->propose(
                    GlossaryTaggableTypeProvider::ITEM_TYPE,
                    $id,
                    $this->getAuthedUser(),
                    $this->buildChanges($newGlossary, $id, $tagIds),
                );
            }

            return $this->redirectToRoute('app_plugin_glossary');
        }

        $pendingProposals = [];
        foreach ($this->changeProposalService->pendingForTarget(GlossaryTaggableTypeProvider::ITEM_TYPE, (int) $id) as $proposal) {
            $pendingProposals[] = [
                'proposal' => $proposal,
                'rows' => $this->changeProposalService->fieldRows($proposal),
            ];
        }

        return $this->renderPage('@Glossary/edit.html.twig', [
            'editItem' => $this->service->getManaged($id),
            'pendingProposals' => $pendingProposals,
            'form' => $form,
        ]);
    }

    /**
     * @param  list<int>         $tagIds
     * @return list<FieldChange>
     */
    private function buildChanges(Glossary $submitted, int $id, array $tagIds): array
    {
        $current = $this->service->getManaged($id);
        if ($current === null) {
            return [];
        }

        return [
            new FieldChange(GlossaryChangeTarget::FIELD_PHRASE, $current->getPhrase(), $submitted->getPhrase()),
            new FieldChange(GlossaryChangeTarget::FIELD_PINYIN, $current->getPinyin(), $submitted->getPinyin()),
            new FieldChange(GlossaryChangeTarget::FIELD_EXPLANATION, $current->getExplanation(), $submitted->getExplanation()),
            new FieldChange(
                GlossaryChangeTarget::FIELD_TAG,
                $this->service->encodeTagIds($this->service->getTagIds($id)),
                $this->service->encodeTagIds($tagIds),
            ),
        ];
    }
}
