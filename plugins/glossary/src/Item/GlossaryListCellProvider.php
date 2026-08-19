<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Enum\ItemViewType;
use App\Item\ListCellProviderInterface;
use App\Item\Tag\TagService;
use App\Item\ListProviderInterface;
use App\Review\ChangeProposalService;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

final readonly class GlossaryListCellProvider implements ListCellProviderInterface, ListProviderInterface
{
    public function __construct(
        private GlossaryService $glossaryService,
        private ConfigService $configService,
        private TagService $tagService,
        private Environment $twig,
        private ChangeProposalService $changeProposalService,
        private Security $security,
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
        $entry = $this->glossaryService->get($itemId);
        if ($entry === null) {
            return null;
        }

        return $this->twig->render('@Glossary/item/list_cell.html.twig', [
            'entry' => $entry,
            'viewMode' => $mode?->value,
            'config' => $this->configService->getConfig(),
            'hasTags' => $this->hasTags(),
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        $entries = $this->glossaryService->getList();

        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return array_values(array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $entries));
        }

        $pendingProposalIds = $this->changeProposalService->pendingTargetIds(GlossaryTaggableTypeProvider::ITEM_TYPE);

        $needsAttention = [];
        $rest = [];
        foreach ($entries as $entry) {
            if (!$entry->getApproved() || in_array((int) $entry->getId(), $pendingProposalIds, true)) {
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

    private function hasTags(): bool
    {
        return $this->tagService->getVocabulary(GlossaryTaggableTypeProvider::ITEM_TYPE) !== [];
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
}
