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
use Twig\Environment;

final class GlossaryListCellProvider implements ListCellProviderInterface, ListProviderInterface
{
    /** @var array<int, Glossary>|null */
    private ?array $entries = null;

    private ?bool $hasTags = null;

    public function __construct(
        private readonly GlossaryService $glossaryService,
        private readonly ConfigService $configService,
        private readonly TagService $tagService,
        private readonly Environment $twig,
        private readonly ChangeProposalService $changeProposalService,
        private readonly Security $security,
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
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        $entries = $this->glossaryService->getList();
        $this->entries ??= $this->byId($entries);

        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return array_values(array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $entries));
        }

        $pendingProposalIds = $this->changeProposalService->pendingTargetIds(GlossaryTaggableTypeProvider::ITEM_TYPE);

        $needsAttention = [];
        $rest = [];
        foreach ($entries as $entry) {
            if (in_array((int) $entry->getId(), $pendingProposalIds, true)) {
                $needsAttention[] = (int) $entry->getId();
                continue;
            }

            $rest[] = (int) $entry->getId();
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
