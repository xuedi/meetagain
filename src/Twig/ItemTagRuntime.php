<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\Tag\ChangeTarget;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use Module\Proposal\Contract\Change\ChangeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ItemTagRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private TagService $tagService,
        private TypeRegistry $registry,
        private RequestStack $requestStack,
        private ChangeInterface $changeProposals,
    ) {}

    /** @return list<string> */
    public function tagLabels(string $itemType, int $itemId): array
    {
        return $this->tagService->getLabels($itemType, $itemId, $this->locale());
    }

    /** @return array<int, string> */
    public function tagChoices(string $itemType): array
    {
        return $this->tagService->getChoices($itemType, $this->locale());
    }

    /** @return list<array{id: int, label: string, depth: int}> */
    public function tagRows(string $itemType): array
    {
        return $this->tagService->getChoiceRows($itemType, $this->locale());
    }

    public function pendingCount(string $itemType): int
    {
        if (!$this->registry->has($itemType)) {
            return 0;
        }

        return $this->changeProposals->countPending(ChangeTarget::TYPE_PREFIX . $itemType);
    }

    private function locale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
