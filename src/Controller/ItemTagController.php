<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Form\Item\TagsType;
use App\Item\Tag\ChangeTarget;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use App\Review\ChangeProposalService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STEWARD')]
final class ItemTagController extends AbstractController
{
    public function __construct(
        private readonly TypeRegistry $registry,
        private readonly TagService $tagService,
        private readonly ChangeProposalService $changeProposals,
    ) {}

    #[Route('/item/{itemType}/tags', name: 'app_item_tags', methods: ['GET', 'POST'])]
    public function tags(Request $request, string $itemType): Response
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(
            TagsType::class,
            ['tags' => $this->editorRows($itemType)],
            [
                'usage' => $this->tagService->getUsage($itemType),
                'depths' => $this->tagService->getDepths($itemType),
                'parent_choices' => $this->parentChoices($itemType),
            ],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tagService->saveVocabulary($itemType, array_values((array) ($form->getData()['tags'] ?? [])));
            $this->addFlash('success', 'item.tag_flash_saved');

            return $this->redirectToRoute('app_item_tags', ['itemType' => $itemType]);
        }

        return $this->render('item/tags.html.twig', [
            'itemType' => $itemType,
            'typeLabelKey' => $provider->getLabelKey(),
            'targetType' => ChangeTarget::TYPE_PREFIX . $itemType,
            'pending' => $this->pendingCards(ChangeTarget::TYPE_PREFIX . $itemType),
            'form' => $form,
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
}
