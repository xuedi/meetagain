<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use App\Review\ChangeProposalService;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class GlossaryListCellProvider implements ListCellProviderInterface, ListProviderInterface
{
    public function __construct(
        private GlossaryService $glossaryService,
        private ConfigService $configService,
        private Environment $twig,
        private ChangeProposalService $changeProposalService,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getKey(): string
    {
        return GlossaryCategorizableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function renderListCell(int $itemId): ?string
    {
        $entry = $this->glossaryService->get($itemId);
        if ($entry === null) {
            return null;
        }

        return $this->twig->render('@Glossary/item/list_cell.html.twig', [
            'entry' => $entry,
            'config' => $this->configService->getConfig(),
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        $entries = $this->glossaryService->getList();

        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return array_values(array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $entries));
        }

        $pendingProposalIds = $this->changeProposalService->pendingTargetIds(GlossaryCategorizableTypeProvider::ITEM_TYPE);

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
        ]);
    }

    #[Override]
    public function getListUrl(): string
    {
        return $this->urlGenerator->generate('app_plugin_glossary');
    }
}
