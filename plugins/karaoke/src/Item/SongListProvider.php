<?php declare(strict_types=1);

namespace Plugin\Karaoke\Item;

use App\Enum\ItemViewType;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use Override;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Service\SongService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Languages;
use Twig\Environment;

final class SongListProvider implements ListCellProviderInterface, ListProviderInterface
{
    /** @var array<int, Song>|null */
    private ?array $songs = null;

    public function __construct(
        private readonly SongService $songService,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    #[Override]
    public function getKey(): string
    {
        return SongService::ITEM_TYPE;
    }

    #[Override]
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string
    {
        $song = $this->songs[$itemId] ?? $this->songService->get($itemId);
        if ($song === null) {
            return null;
        }

        return $this->twig->render('@Karaoke/item/list_cell.html.twig', [
            'song' => $song,
            'languageName' => Languages::getName((string) $song->getLanguage(), $this->requestStack->getCurrentRequest()?->getLocale()),
            'viewMode' => $mode?->value,
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        $songs = $this->songService->getList();
        $ids = array_map(static fn(Song $song): int => (int) $song->getId(), $songs);
        $this->songs ??= array_combine($ids, $songs);

        return $ids;
    }

    #[Override]
    public function renderList(): string
    {
        return $this->twig->render('@Karaoke/item/list_body.html.twig', [
            'itemIds' => $this->getItemIds(),
        ]);
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_plugin_karaoke';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_karaoke_show';
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
        foreach ($this->songService->getList() as $song) {
            $id = (int) $song->getId();
            $createdAt = $song->getCreatedAt();
            if ($createdAt === null || !isset($wanted[$id])) {
                continue;
            }

            $stamps[$id] = $createdAt;
        }

        return $stamps;
    }
}
