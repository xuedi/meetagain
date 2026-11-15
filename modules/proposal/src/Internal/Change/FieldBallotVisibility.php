<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Change;

use Module\Ballot\Contract\VisibilityFilterInterface;
use Module\Proposal\Contract\Change\ChangeInterface;
use Override;

final readonly class FieldBallotVisibility implements VisibilityFilterInterface
{
    public function __construct(
        private Registry $targets,
    ) {}

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function narrowVisibleBallotIds(string $purpose, array $ballots, ?int $viewerUserId): ?array
    {
        if ($purpose !== ChangeInterface::BALLOT_PURPOSE) {
            return null;
        }

        $visible = [];
        foreach ($ballots as $ballot) {
            $subject = $ballot->subject;
            $provider = $subject === null ? null : $this->targets->providerFor($subject->type);
            if ($provider !== null && $provider->isVisible($subject->id)) {
                $visible[] = $ballot->id;
            }
        }

        return $visible;
    }
}
