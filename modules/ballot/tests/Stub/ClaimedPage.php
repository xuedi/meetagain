<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Stub;

use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\PageProviderInterface;
use Override;

class ClaimedPage implements PageProviderInterface
{
    public const string PURPOSE = 'test.claimed';

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === self::PURPOSE;
    }

    #[Override]
    public function url(BallotView $ballot): string
    {
        return '/elsewhere/' . $ballot->id;
    }
}
