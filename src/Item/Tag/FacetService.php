<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Item\ListRegistry;
use App\Repository\ItemTagAssignmentRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class FacetService
{
    private bool $suppressed = false;

    /** @var array<string, array{tags: array<int, int>, total: int, shown: int}|null> */
    private array $memo = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ListRegistry $listRegistry,
        private readonly TagService $tagService,
        private readonly ItemTagAssignmentRepository $assignmentRepo,
    ) {}

    public function current(): FacetSelection
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($this->suppressed || $request === null) {
            return new FacetSelection();
        }

        return $this->fromQuery($request->query->all());
    }

    public function urlFor(string $baseUrl, FacetSelection $selection): string
    {
        $query = http_build_query($selection->toQuery());

        return $baseUrl . ($query !== '' ? '?' . $query : '');
    }

    public function withoutFacets(callable $callback): mixed
    {
        $previous = $this->suppressed;
        $this->suppressed = true;

        try {
            return $callback();
        } finally {
            $this->suppressed = $previous;
        }
    }

    /** @return array{tags: array<int, int>, total: int, shown: int}|null */
    public function counts(string $itemType): ?array
    {
        if (array_key_exists($itemType, $this->memo)) {
            return $this->memo[$itemType];
        }

        return $this->memo[$itemType] = $this->compute($itemType);
    }

    /** @return array{tags: array<int, int>, total: int, shown: int}|null */
    private function compute(string $itemType): ?array
    {
        $listProvider = $this->listRegistry->providerFor($itemType);
        if ($listProvider === null || $this->tagService->getVocabulary($itemType) === []) {
            return null;
        }

        $visible = $this->withoutFacets($listProvider->getItemIds(...));
        $selection = $this->current();

        $result = $selection->tags === []
            ? $visible
            : array_values(array_intersect($visible, $this->assignmentRepo->itemIdsWithAllTags($itemType, $selection->tags)));

        return [
            'tags' => $this->assignmentRepo->countsByTag($itemType, $result),
            'total' => count($visible),
            'shown' => count($result),
        ];
    }

    /** @param array<string, mixed> $query */
    private function fromQuery(array $query): FacetSelection
    {
        $raw = $query['tag'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $tags = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $tags[] = (int) $value;
        }

        return new FacetSelection(array_values(array_unique($tags)));
    }
}
