<?php declare(strict_types=1);

namespace Module\Ballot\Internal;

use Module\Ballot\Contract\BallotScope;
use Module\Ballot\Contract\VisibilityFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class VisibilityFilterService
{
    /**
     * @param iterable<VisibilityFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(VisibilityFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * @param  list<BallotScope> $scopes
     * @return list<int>
     */
    public function narrow(string $purpose, array $scopes, ?int $viewerUserId): array
    {
        $visible = array_map(static fn(BallotScope $scope): int => $scope->id, $scopes);

        foreach ($this->sorted() as $filter) {
            $narrowed = $filter->narrowVisibleBallotIds($purpose, $this->only($scopes, $visible), $viewerUserId);
            if ($narrowed === null) {
                continue;
            }

            $visible = array_values(array_intersect($visible, $narrowed));
            if ($visible === []) {
                return [];
            }
        }

        return $visible;
    }

    public function allows(BallotScope $scope, ?int $viewerUserId): bool
    {
        return $this->narrow($scope->purpose, [$scope], $viewerUserId) !== [];
    }

    /**
     * @param  list<BallotScope> $scopes
     * @param  list<int>         $ids
     * @return list<BallotScope>
     */
    private function only(array $scopes, array $ids): array
    {
        return array_values(array_filter($scopes, static fn(BallotScope $scope): bool => in_array($scope->id, $ids, true)));
    }

    /**
     * @return list<VisibilityFilterInterface>
     */
    private function sorted(): array
    {
        $filters = array_values(iterator_to_array($this->filters));
        usort($filters, static fn(VisibilityFilterInterface $a, VisibilityFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
