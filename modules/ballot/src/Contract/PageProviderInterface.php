<?php declare(strict_types=1);

namespace Module\Ballot\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Claims the page a purpose's ballots are voted on. First match wins; with no provider the module's
 * own page is used.
 */
#[AutoconfigureTag]
interface PageProviderInterface
{
    public function supports(string $purpose): bool;

    public function url(BallotView $ballot): string;
}
