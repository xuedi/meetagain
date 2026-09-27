<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Stub;

use Module\Ballot\Contract\BallotScope;
use Module\Ballot\Contract\VisibilityFilterInterface;
use Override;

class BlindfoldVisibilityFilter implements VisibilityFilterInterface
{
    /** @var list<int> */
    public array $hiddenBallotIds = [];

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function narrowVisibleBallotIds(string $purpose, array $ballots, ?int $viewerUserId): ?array
    {
        if ($this->hiddenBallotIds === []) {
            return null;
        }

        $ids = array_map(static fn(BallotScope $ballot): int => $ballot->id, $ballots);

        return array_values(array_diff($ids, $this->hiddenBallotIds));
    }
}
