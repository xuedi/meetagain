<?php declare(strict_types=1);

namespace App\Controller;

use App\Contribution\Registry;
use App\Contribution\RowFormInterface;
use App\Contribution\Section;
use App\Contribution\TagSection;
use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Item\Tag\ChangeTarget;
use App\Item\Tag\SuggestionBuilder;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use App\Review\ChangeProposalService;
use App\Review\FieldChange;
use App\Suggestion\SuggestionException;
use App\Suggestion\SuggestionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contribute')]
#[IsGranted('ROLE_USER')]
final class ContributionController extends AbstractController
{
    public function __construct(
        private readonly Registry $registry,
        private readonly SuggestionService $suggestionService,
        private readonly ChangeProposalService $changeProposalService,
        private readonly TypeRegistry $tagTypes,
        private readonly TagService $tagService,
        private readonly SuggestionBuilder $suggestionBuilder,
    ) {}

    #[Route('', name: 'app_contribution_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('contribution/index.html.twig', $this->shell($user, null, null, null));
    }

    #[Route('/{type}', name: 'app_contribution_section', methods: ['GET'])]
    public function section(string $type, #[CurrentUser] User $user): Response
    {
        if (!$this->registry->has($type)) {
            throw $this->createNotFoundException();
        }

        return $this->render('contribution/index.html.twig', $this->shell($user, null, $type, null));
    }

    #[Route('/{type}/suggest', name: 'app_contribution_suggest', methods: ['GET', 'POST'])]
    public function suggest(Request $request, string $type, #[CurrentUser] User $user): Response
    {
        if (!$this->registry->has($type) || !$this->suggestionService->hasProvider($type)) {
            throw $this->createNotFoundException();
        }

        $provider = $this->suggestionService->providerFor($type);
        if (!$provider->canPropose($user)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm($provider->getFormType(), $provider->newDraft());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->suggestionService->propose($type, $user, $form->getData());
                $this->addFlash('success', 'contribution.flash_suggested');

                return $this->redirectToRoute('app_contribution_suggest', ['type' => $type]);
            } catch (SuggestionException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(
            'contribution/index.html.twig',
            $this->shell($user, 'suggest', $type, null)
            + [
                'form' => $form,
                'typeLabelKey' => $provider->getLabelKey(),
                'pending' => $this->pendingSuggestionCards($user, $type),
            ],
        );
    }

    #[Route('/{type}/{id}', name: 'app_contribution_correct', requirements: ['id' => '[^/]+'], methods: ['GET', 'POST'])]
    public function correct(Request $request, string $type, string $id, #[CurrentUser] User $user): Response
    {
        if (!$this->registry->has($type) || !$this->registry->mayTouch($type, $user, $id)) {
            throw $this->createNotFoundException();
        }

        $provider = $this->registry->providerFor($type);

        return match (true) {
            $provider instanceof RowFormInterface => $this->correctRow($request, $provider, $id, $user),
            $provider instanceof TagSection => $this->correctTags($request, $id, $user),
            default => throw $this->createNotFoundException(),
        };
    }

    private function correctRow(Request $request, RowFormInterface $provider, string $id, User $user): Response
    {
        $draft = $provider->draftFor($id);
        if ($draft === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm($provider->getFormType(), $draft->data, $draft->formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $proposal = $this->changeProposalService->propose($provider->getTargetType(), (int) $id, $user, $provider->changesFrom($id, $form));
            $this->addFlash('success', $proposal === null ? 'contribution.flash_unchanged' : 'contribution.flash_proposed');

            return $this->redirectToRoute('app_contribution_correct', ['type' => $provider->getType(), 'id' => $id]);
        }

        return $this->render(
            'contribution/index.html.twig',
            $this->shell($user, 'correct', $provider->getType(), $id)
            + [
                'form' => $form,
                'draft' => $draft,
                'pendingProposals' => $this->pendingProposalCards($provider->getTargetType(), (int) $id),
            ],
        );
    }

    private function correctTags(Request $request, string $itemType, User $user): Response
    {
        $targetType = ChangeTarget::TYPE_PREFIX . $itemType;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('item_tag_suggest', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $proposed = 0;
            foreach ($this->submittedTagChanges($request, $itemType) as $targetId => $changes) {
                $proposed += $this->changeProposalService->propose($targetType, $targetId, $user, $changes) === null ? 0 : 1;
            }
            $this->addFlash($proposed === 0 ? 'info' : 'success', $proposed === 0 ? 'item.tag_flash_unchanged' : 'item.tag_flash_suggested');

            return $this->redirectToRoute('app_contribution_correct', ['type' => TagSection::TYPE, 'id' => $itemType]);
        }

        return $this->render(
            'contribution/index.html.twig',
            $this->shell($user, 'correct_tags', TagSection::TYPE, $itemType)
            + [
                'itemType' => $itemType,
                'typeLabelKey' => (string) $this->tagTypes->providerFor($itemType)?->getLabelKey(),
                'rows' => $this->suggestionBuilder->rows($itemType, $request->getLocale()),
                'usage' => $this->tagService->getUsage($itemType),
                'pendingProposals' => $this->pendingTagProposalCards($targetType),
            ],
        );
    }

    /**
     * @return array<int, list<FieldChange>>
     */
    private function submittedTagChanges(Request $request, string $itemType): array
    {
        $submitted = $request->request->all('suggest');

        $addedBelow = [];
        foreach ((array) ($submitted['addBelow'] ?? []) as $parent => $labels) {
            $addedBelow[$parent] = array_values(array_map(strval(...), (array) $labels));
        }

        return $this->suggestionBuilder->changes(
            $itemType,
            $request->getLocale(),
            array_map(strval(...), (array) ($submitted['edit'] ?? [])),
            array_values(array_map(strval(...), (array) ($submitted['add'] ?? []))),
            $addedBelow,
        );
    }

    /**
     * @return list<array{proposal: ChangeProposal, rows: list<array<string, mixed>>}>
     */
    private function pendingTagProposalCards(string $targetType): array
    {
        $cards = [];
        foreach ($this->changeProposalService->pendingTargetIds($targetType) as $targetId) {
            $cards = [...$cards, ...$this->pendingProposalCards($targetType, $targetId)];
        }

        return $cards;
    }

    /**
     * @return array{sections: list<Section>, suggestable: array<string, string>, activeType: ?string, pane: ?string, activeId: int|string|null}
     */
    private function shell(User $user, ?string $pane, ?string $activeType, int|string|null $activeId): array
    {
        $sections = $this->registry->sectionsFor($user);

        return [
            'sections' => $sections,
            'suggestable' => $this->suggestableTypes($sections, $user),
            'activeType' => $activeType,
            'pane' => $pane,
            'activeId' => $activeId,
        ];
    }

    /**
     * @param  list<Section>         $sections
     * @return array<string, string> section type => translation key of the row's own label
     */
    private function suggestableTypes(array $sections, User $user): array
    {
        $types = [];
        foreach ($sections as $section) {
            if (!$this->suggestionService->hasProvider($section->type)) {
                continue;
            }

            $provider = $this->suggestionService->providerFor($section->type);
            if ($provider->canPropose($user)) {
                $types[$section->type] = $provider->getLabelKey();
            }
        }

        return $types;
    }

    /**
     * @return list<array{id: int, description: string, rows: list<array{label: string, value: string}>}>
     */
    private function pendingSuggestionCards(User $user, string $targetType): array
    {
        $cards = [];
        foreach ($this->suggestionService->pendingFor($user) as $suggestion) {
            if ($suggestion->getTargetType() !== $targetType) {
                continue;
            }

            $cards[] = [
                'id' => (int) $suggestion->getId(),
                'description' => $this->suggestionService->describe($suggestion),
                'rows' => $this->suggestionService->summaryRows($suggestion),
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{proposal: ChangeProposal, rows: list<array<string, mixed>>}>
     */
    private function pendingProposalCards(string $targetType, int $id): array
    {
        $cards = [];
        foreach ($this->changeProposalService->pendingForTarget($targetType, $id) as $proposal) {
            $cards[] = [
                'proposal' => $proposal,
                'rows' => $this->changeProposalService->fieldRows($proposal),
            ];
        }

        return $cards;
    }
}
