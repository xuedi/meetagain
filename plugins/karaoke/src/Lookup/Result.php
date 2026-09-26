<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

use Plugin\Karaoke\Enum\LookupFailure;

final readonly class Result
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        public array $candidates,
        public ?LookupFailure $failure = null,
    ) {}
}
