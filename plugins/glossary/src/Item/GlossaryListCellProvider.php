<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Enum\ItemViewType;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use App\Item\Tag\TagService;
use App\Review\ChangeProposalService;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class GlossaryListCellProvider implements ListCellProviderInterface, ListProviderInterface
{
    /** @var array<int, Glossary>|null */
    private ?array $entries = null;

    private ?bool $hasTags = null;

    /** @var list<int> */
    private array $listedIds = [];

    /** @var array<int, list<string>> */
    private array $tagLabels = [];

    /** @var array<int, true> */
    private array $tagLabelsLoaded = [];

    /** @var array<int, int>|null */
    private ?array $pendingProposalIds = null;

    public function __construct(
        private readonly GlossaryService $glossaryService,
        private readonly ConfigService $configService,
        private readonly TagService $tagService,
        private readonly Environment $twig,
        private readonly ChangeProposalService $changeProposalService,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getKey(): string
    {
        return GlossaryTaggableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string
    {
        $entry = $this->entries()[$itemId] ?? $this->glossaryService->get($itemId);
        if ($entry === null) {
            return null;
        }

        return $this->twig->render('@Glossary/item/list_cell.html.twig', [
            'entry' => $entry,
            'definition' => $this->glossaryService->definitionFor($entry),
            'viewMode' => $mode?->value,
            'config' => $this->configService->getConfig(),
            'hasTags' => $this->hasTags(),
            'tagLabels' => $this->tagLabelsFor($itemId),
            'hasPendingProposal' => isset($this->pendingProposalIds()[$itemId]),
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        $entries = $this->glossaryService->getList();
        $this->entries ??= $this->byId($entries);
        $this->listedIds = array_values(array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $entries));

        $pendingProposalIds = $this->pendingProposalIds();
        if ($pendingProposalIds === []) {
            return $this->listedIds;
        }

        $needsAttention = [];
        $rest = [];
        foreach ($this->listedIds as $itemId) {
            if (isset($pendingProposalIds[$itemId])) {
                $needsAttention[] = $itemId;
                continue;
            }

            $rest[] = $itemId;
        }

        return [...$needsAttention, ...$rest];
    }

    #[Override]
    public function renderList(): string
    {
        return $this->twig->render('@Glossary/item/list_body.html.twig', [
            'itemIds' => $this->getItemIds(),
            'config' => $this->configService->getConfig(),
            'hasTags' => $this->hasTags(),
        ]);
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_plugin_glossary';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_glossary_show';
    }

    #[Override]
    public function isDetailIndexable(): bool
    {
        return false;
    }

    #[Override]
    public function getLastmodByItemId(array $itemIds): array
    {
        $wanted = array_flip($itemIds);

        $stamps = [];
        foreach ($this->glossaryService->getList() as $entry) {
            $id = (int) $entry->getId();
            $createdAt = $entry->getCreatedAt();
            if ($createdAt === null || !isset($wanted[$id])) {
                continue;
            }

            $stamps[$id] = $createdAt;
        }

        return $stamps;
    }

    private function hasTags(): bool
    {
        return $this->hasTags ??= $this->tagService->getVocabulary(GlossaryTaggableTypeProvider::ITEM_TYPE) !== [];
    }

    /** @return list<string> */
    private function tagLabelsFor(int $itemId): array
    {
        if (!$this->hasTags()) {
            return [];
        }

        if (!isset($this->tagLabelsLoaded[$itemId])) {
            $itemIds = array_values(array_unique([...$this->listedIds, $itemId]));
            $this->tagLabels += $this->tagService->getLabelsForItems(
                GlossaryTaggableTypeProvider::ITEM_TYPE,
                $itemIds,
                $this->requestStack->getCurrentRequest()?->getLocale(),
            );
            $this->tagLabelsLoaded += array_fill_keys($itemIds, true);
        }

        return $this->tagLabels[$itemId] ?? [];
    }

    /** @return array<int, int> */
    private function pendingProposalIds(): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        return $this->pendingProposalIds ??= array_flip($this->changeProposalService->pendingTargetIds(GlossaryTaggableTypeProvider::ITEM_TYPE));
    }

    /** @return array<int, Glossary> */
    private function entries(): array
    {
        return $this->entries ??= $this->byId($this->glossaryService->getList());
    }

    /**
     * @param Glossary[] $entries
     * @return array<int, Glossary>
     */
    private function byId(array $entries): array
    {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[(int) $entry->getId()] = $entry;
        }

        return $byId;
    }
}
