<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Form\Item\TaxonomyDefinitionsType;
use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ChangeTarget;
use App\Item\Taxonomy\Config;
use App\Item\Taxonomy\ScopeCodec;
use App\Item\Taxonomy\ScopedSettings;
use App\Item\Taxonomy\SuggestionBuilder;
use App\Item\Taxonomy\UsageCounter;
use App\Publisher\PluginSettings\Resolver;
use App\Review\ChangeProposalService;
use App\Review\FieldChange;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ItemTaxonomyController extends AbstractController
{
    public function __construct(
        private readonly CategorizableTypeRegistry $registry,
        private readonly Resolver $resolver,
        private readonly ScopedSettings $settings,
        private readonly ScopeCodec $scopeCodec,
        private readonly SuggestionBuilder $suggestionBuilder,
        private readonly UsageCounter $usageCounter,
        private readonly ChangeProposalService $changeProposals,
    ) {}

    #[Route('/item/{itemType}/taxonomy', name: 'app_item_taxonomy', methods: ['GET', 'POST'])]
    public function taxonomy(Request $request, string $itemType, #[CurrentUser] User $user): Response
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        $scopeId = $this->resolver->resolveScopeId();
        $settings = $this->isGranted($scopeId === null ? 'ROLE_ADMIN' : 'ROLE_STEWARD')
            ? $this->settings->load($provider->getSettingsKey(), $scopeId)
            : null;

        if ($settings !== null) {
            return $this->manage($request, $provider, $settings, $scopeId);
        }

        return $this->suggest($request, $provider, $user);
    }

    private function manage(Request $request, CategorizableTypeProviderInterface $provider, object $settings, ?string $scopeId): Response
    {
        $taxonomy = $provider->taxonomyOf($settings);
        $form = $this->createForm(TaxonomyDefinitionsType::class, $taxonomy, [
            'with_categories' => $provider->supportsCategories(),
            'with_tags' => $provider->supportsTags(),
            'usage' => $this->usage($provider),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taxonomy->normalize();
            $this->settings->save($provider->getSettingsKey(), $settings, $scopeId);
            $this->addFlash('success', 'item.taxonomy_flash_saved');

            return $this->redirectToRoute('app_item_taxonomy', ['itemType' => $provider->getTypeKey()]);
        }

        return $this->page($request, $provider, $taxonomy, ['form' => $form, 'canManage' => true]);
    }

    private function suggest(Request $request, CategorizableTypeProviderInterface $provider, User $user): Response
    {
        $taxonomy = $provider->getTaxonomy();
        $targetId = $this->scopeCodec->currentTargetId();

        if ($request->isMethod('POST') && $targetId !== null) {
            if (!$this->isCsrfTokenValid('item_taxonomy_suggest', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $proposal = $this->changeProposals->propose(
                ChangeTarget::TYPE_PREFIX . $provider->getTypeKey(),
                $targetId,
                $user,
                $this->submittedChanges($request, $provider, $taxonomy),
            );
            $this->addFlash(
                $proposal === null ? 'info' : 'success',
                $proposal === null ? 'item.taxonomy_flash_unchanged' : 'item.taxonomy_flash_suggested',
            );

            return $this->redirectToRoute('app_item_taxonomy', ['itemType' => $provider->getTypeKey()]);
        }

        return $this->page($request, $provider, $taxonomy, ['canSuggest' => $targetId !== null]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function page(Request $request, CategorizableTypeProviderInterface $provider, Config $taxonomy, array $extra): Response
    {
        $targetId = $this->scopeCodec->currentTargetId();
        $targetType = ChangeTarget::TYPE_PREFIX . $provider->getTypeKey();

        $axes = [];
        $disabled = [];
        foreach ($this->supportedAxes($provider) as $axis) {
            $axes[] = [
                'key' => $axis->value,
                'headingKey' => $this->headingKey($axis),
                'emptyKey' => $axis === Axis::Category ? 'item.taxonomy_categories_empty' : 'item.taxonomy_tags_empty',
                'rows' => $this->suggestionBuilder->rows($taxonomy, $axis, $request->getLocale()),
                'usage' => $this->usageCounter->counts($provider->getTypeKey(), $axis),
                'enabled' => $taxonomy->isEnabled($axis),
            ];
            if ($taxonomy->isEnabled($axis)) {
                continue;
            }

            $disabled[] = $this->headingKey($axis);
        }

        return $this->render('item/taxonomy.html.twig', [
            'itemType' => $provider->getTypeKey(),
            'typeLabelKey' => $provider->getLabelKey(),
            'axes' => $axes,
            'disabledAxisKeys' => $disabled,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'pending' => $this->pendingCards($targetType, $targetId),
            'form' => null,
            'canManage' => false,
            'canSuggest' => false,
            ...$extra,
        ]);
    }

    /** @return array<string, array<int, int>> */
    private function usage(CategorizableTypeProviderInterface $provider): array
    {
        $usage = [];
        foreach ($this->supportedAxes($provider) as $axis) {
            $usage[$axis->value] = $this->usageCounter->counts($provider->getTypeKey(), $axis);
        }

        return $usage;
    }

    /**
     * @return list<array{proposal: ChangeProposal, rows: list<array<string, mixed>>, isProposer: bool}>
     */
    private function pendingCards(string $targetType, ?int $targetId): array
    {
        if ($targetId === null) {
            return [];
        }

        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $cards = [];
        foreach ($this->changeProposals->pendingForTarget($targetType, $targetId) as $proposal) {
            $cards[] = [
                'proposal' => $proposal,
                'rows' => $this->changeProposals->fieldRows($proposal),
                'isProposer' => $proposal->getProposedBy()->getId() === $userId,
            ];
        }

        return $cards;
    }

    /** @return list<FieldChange> */
    private function submittedChanges(Request $request, CategorizableTypeProviderInterface $provider, Config $taxonomy): array
    {
        $submitted = $request->request->all('suggest');

        $changes = [];
        foreach ($this->supportedAxes($provider) as $axis) {
            if (!$taxonomy->isEnabled($axis)) {
                continue;
            }

            $axisInput = (array) ($submitted[$axis->value] ?? []);
            $changes = [...$changes, ...$this->suggestionBuilder->changes(
                $taxonomy,
                $axis,
                $request->getLocale(),
                array_map(strval(...), (array) ($axisInput['edit'] ?? [])),
                array_values(array_map(strval(...), (array) ($axisInput['add'] ?? []))),
            )];
        }

        return $changes;
    }

    /** @return list<Axis> */
    private function supportedAxes(CategorizableTypeProviderInterface $provider): array
    {
        $axes = [];
        if ($provider->supportsCategories()) {
            $axes[] = Axis::Category;
        }
        if ($provider->supportsTags()) {
            $axes[] = Axis::Tag;
        }

        return $axes;
    }

    private function headingKey(Axis $axis): string
    {
        return $axis === Axis::Category ? 'item.taxonomy_categories_heading' : 'item.taxonomy_tags_heading';
    }
}
