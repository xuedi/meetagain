<?php declare(strict_types=1);

namespace Plugin\Karaoke\Item;

use App\Item\Report\ReportableTypeProviderInterface;
use Override;
use Plugin\Karaoke\Repository\SongRepository;
use Plugin\Karaoke\Service\SongService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SongReportableTypeProvider implements ReportableTypeProviderInterface
{
    public function __construct(
        private SongRepository $songRepo,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return SongService::ITEM_TYPE;
    }

    #[Override]
    public function getItemLabel(int $itemId): ?string
    {
        return $this->songRepo->find($itemId)?->getTitle();
    }

    #[Override]
    public function getItemPath(int $itemId): string
    {
        return $this->urlGenerator->generate('app_plugin_karaoke_show', ['id' => $itemId]);
    }
}
