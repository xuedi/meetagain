<?php declare(strict_types=1);

namespace Plugin\Voting\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class PollFilterService
{
    /**
     * @param iterable<PollFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(PollFilterInterface::class)]
        private iterable $filters,
    ) {}

    /** @return list<int>|null */
    public function getAllowedPollIds(): ?array
    {
        $result = null;

        foreach ($this->filters as $filter) {
            $ids = $filter->getAllowedPollIds();
            if ($ids === null) {
                continue;
            }

            $result = $result === null ? array_values($ids) : array_values(array_intersect($result, $ids));
        }

        return $result;
    }

    public function isAllowed(int $pollId): bool
    {
        $allowed = $this->getAllowedPollIds();

        return $allowed === null || in_array($pollId, $allowed, true);
    }
}
