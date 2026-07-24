<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use App\Review\ChangeProposalService;
use App\Review\FieldChange;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
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
    ) {
        parent::__construct($service);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        $newGlossary = $this->service->get($id);

        $form = $this->createForm(GlossaryType::class, $newGlossary);
        if ($form->has('category') && !$request->isMethod('POST')) {
            $form->get('category')->setData($this->service->getCategory($id));
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->detach($newGlossary); // dont auto save entity
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }

            $categoryId = $form->has('category') ? $this->intOrNull($form->get('category')->getData()) : null;

            if ($this->isGranted('ROLE_ORGANIZER')) {
                // Managers can update directly
                $this->service->update($newGlossary, $id, $categoryId);
            } else {
                // Regular users propose changes for review
                $this->changeProposalService->propose(
                    GlossaryCategorizableTypeProvider::ITEM_TYPE,
                    $id,
                    $this->getAuthedUser(),
                    $this->buildChanges($newGlossary, $id, $categoryId),
                );
            }

            return $this->redirectToRoute('app_plugin_glossary');
        }

        $pendingProposals = [];
        foreach ($this->changeProposalService->pendingForTarget(GlossaryCategorizableTypeProvider::ITEM_TYPE, (int) $id) as $proposal) {
            $pendingProposals[] = [
                'proposal' => $proposal,
                'rows' => $this->changeProposalService->fieldRows($proposal),
            ];
        }

        return $this->renderPage('@Glossary/edit.html.twig', [
            'editItem' => $this->service->get($id),
            'pendingProposals' => $pendingProposals,
            'form' => $form,
        ]);
    }

    /** @return list<FieldChange> */
    private function buildChanges(Glossary $submitted, int $id, ?int $categoryId): array
    {
        $current = $this->service->get($id);
        if ($current === null) {
            return [];
        }

        $currentCategory = $this->service->getCategory($id);

        return [
            new FieldChange(GlossaryChangeTarget::FIELD_PHRASE, $current->getPhrase(), $submitted->getPhrase()),
            new FieldChange(GlossaryChangeTarget::FIELD_PINYIN, $current->getPinyin(), $submitted->getPinyin()),
            new FieldChange(GlossaryChangeTarget::FIELD_EXPLANATION, $current->getExplanation(), $submitted->getExplanation()),
            new FieldChange(
                GlossaryChangeTarget::FIELD_CATEGORY,
                $currentCategory === null ? null : (string) $currentCategory,
                $categoryId === null ? null : (string) $categoryId,
            ),
        ];
    }
}
