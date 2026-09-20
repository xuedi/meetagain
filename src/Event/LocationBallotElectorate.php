<?php declare(strict_types=1);

namespace App\Event;

use App\Filter\Event\EventFilterService;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\ElectorateProviderInterface;
use Override;

final readonly class LocationBallotElectorate implements ElectorateProviderInterface
{
    public function __construct(
        private EventFilterService $eventFilter,
    ) {}

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === BallotLocationChoice::PURPOSE;
    }

    #[Override]
    public function mayVote(int $userId, ?BallotSubject $subject): bool
    {
        return $subject !== null && $subject->type === BallotLocationChoice::SUBJECT_TYPE && $this->eventFilter->isEventAccessible($subject->id);
    }
}
