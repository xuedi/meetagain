<?php declare(strict_types=1);

namespace App\Item\Ballot;

use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\PageProviderInterface;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class Page implements PageProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Purpose $purpose,
    ) {}

    #[Override]
    public function supports(string $purpose): bool
    {
        return $this->purpose->itemTypeOf($purpose) !== null;
    }

    #[Override]
    public function url(BallotView $ballot): string
    {
        return $this->urlGenerator->generate('app_item_ballot_show', ['id' => $ballot->id]);
    }
}
