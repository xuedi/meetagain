<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Form\Item\TagsType;
use App\Item\Tag\ChangeTarget;
use App\Item\Tag\SuggestionBuilder;
use App\Item\Tag\TaggableTypeProviderInterface;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use App\Review\ChangeProposalService;
use App\Review\FieldChange;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ItemTagController extends AbstractController
{
    public function __construct(
        private readonly TypeRegistry $registry,
        private readonly TagService $tagService,
        private readonly SuggestionBuilder $suggestionBuilder,
        private readonly ChangeProposalService $changeProposals,
    ) {}

    #[Route('/item/{itemType}/tags', name: 'app_item_tags', methods: ['GET', 'POST'])]
    public function tags(Request $request, string $itemType, #[CurrentUser] User $user): Response
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        if ($this->isGranted('ROLE_STEWARD')) {
            return $this->manage($request, $provider);
        }

        return $this->suggest($request, $provider, $user);
    }

    private function manage(Request $request, TaggableTypeProviderInterface $provider): Response
    {
        $itemType = $provider->getTypeKey();
        $form = $this->createForm(TagsType::class, ['tags' => $this->editorRows($itemType)], [
            'usage' => $this->tagService->getUsage($itemType),
            'depths' => $this->tagService->getDepths($itemType),
            'parent_choices' => $this->parentChoices($itemType),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tagService->saveVocabulary($itemType, array_values((array) ($form->getData()['tags'] ?? [])));
            $this->addFlash('success', 'item.tag_flash_saved');

            return $this->redirectToRoute('app_item_tags', ['itemType' => $itemType]);
        }

        return $this->page($request, $provider, ['form' => $form, 'canManage' => true]);
    }

    private function suggest(Request $request, TaggableTypeProviderInterface $provider, User $user): Response
    {
        $itemType = $provider->getTypeKey();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('item_tag_suggest', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $proposed = 0;
            foreach ($this->submittedChanges($request, $itemType) as $targetId => $changes) {
                $proposed += $this->changeProposals->propose(ChangeTarget::TYPE_PREFIX . $itemType, $targetId, $user, $changes) === null ? 0 : 1;
            }
            $this->addFlash(
                $proposed === 0 ? 'info' : 'success',
                $proposed === 0 ? 'item.tag_flash_unchanged' : 'item.tag_flash_suggested',
            );

            return $this->redirectToRoute('app_item_tags', ['itemType' => $itemType]);
        }

        return $this->page($request, $provider, ['canSuggest' => true]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function page(Request $request, TaggableTypeProviderInterface $provider, array $extra): Response
    {
        $itemType = $provider->getTypeKey();

        return $this->render('item/tags.html.twig', [
            'itemType' => $itemType,
            'typeLabelKey' => $provider->getLabelKey(),
            'rows' => $this->suggestionBuilder->rows($itemType, $request->getLocale()),
            'usage' => $this->tagService->getUsage($itemType),
            'targetType' => ChangeTarget::TYPE_PREFIX . $itemType,
            'pending' => $this->pendingCards(ChangeTarget::TYPE_PREFIX . $itemType),
            'form' => null,
            'canManage' => false,
            'canSuggest' => false,
            ...$extra,
        ]);
    }

    /** @return list<array{id: int, parent: ?int, labels: array<string, string>}> */
    private function editorRows(string $itemType): array
    {
        $rows = [];
        foreach ($this->tagService->getManagedVocabulary($itemType) as $tag) {
            $rows[] = [
                'id' => (int) $tag->getId(),
                'parent' => $tag->getParent()?->getId(),
                'labels' => $tag->getLabels(),
            ];
        }

        return $rows;
    }

    /** @return array<string, int> */
    private function parentChoices(string $itemType): array
    {
        $choices = [];
        foreach ($this->tagService->getManagedVocabulary($itemType) as $tag) {
            $label = str_repeat('- ', max(0, $tag->getDepth() - 1)) . $this->tagService->labelFor($tag, null);
            $choices[isset($choices[$label]) ? $label . ' #' . $tag->getId() : $label] = (int) $tag->getId();
        }

        return $choices;
    }

    /**
     * @return list<array{proposal: ChangeProposal, targetId: int, rows: list<array<string, mixed>>, isProposer: bool}>
     */
    private function pendingCards(string $targetType): array
    {
        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $cards = [];
        foreach ($this->changeProposals->pendingTargetIds($targetType) as $targetId) {
            foreach ($this->changeProposals->pendingForTarget($targetType, $targetId) as $proposal) {
                $cards[] = [
                    'proposal' => $proposal,
                    'targetId' => $targetId,
                    'rows' => $this->changeProposals->fieldRows($proposal),
                    'isProposer' => $proposal->getProposedBy()->getId() === $userId,
                ];
            }
        }

        return $cards;
    }

    /** @return array<int, list<FieldChange>> */
    private function submittedChanges(Request $request, string $itemType): array
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
}
