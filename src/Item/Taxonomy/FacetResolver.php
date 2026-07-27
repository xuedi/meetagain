<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use Symfony\Component\HttpFoundation\RequestStack;

class FacetResolver
{
    private bool $suppressed = false;

    public function __construct(
        private readonly RequestStack $requestStack,
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

    /** @param array<string, mixed> $query */
    private function fromQuery(array $query): FacetSelection
    {
        $categoryRaw = $query['category'] ?? null;
        $category = is_numeric($categoryRaw) ? (int) $categoryRaw : null;

        $tagsRaw = $query['tag'] ?? [];
        if (!is_array($tagsRaw)) {
            $tagsRaw = [$tagsRaw];
        }

        $tags = [];
        foreach ($tagsRaw as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $tags[] = (int) $value;
        }

        return new FacetSelection($category, array_values(array_unique($tags)));
    }
}
