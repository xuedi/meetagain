<?php declare(strict_types=1);

namespace App\Portability\Ballot\Purpose;

use App\Entity\Event;
use App\Entity\Location;
use App\Event\BallotLocationChoice;
use App\Portability\Ballot\PurposeInterface;
use App\Portability\ImportContext;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Override;

readonly class VenuePurpose implements PurposeInterface
{
    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === BallotLocationChoice::PURPOSE;
    }

    #[Override]
    public function inScope(string $purpose, ?BallotSubject $subject, array $candidateKeys, Scope $scope): bool
    {
        return $subject !== null && $subject->type === BallotLocationChoice::SUBJECT_TYPE && in_array($subject->id, $scope->eventIds, true);
    }

    #[Override]
    public function importSubject(BallotSubject $subject, ImportContext $context): ?BallotSubject
    {
        $eventId = $context->resolveRef(Event::class, $subject->id)?->getId();

        return $eventId === null ? null : new BallotSubject($subject->type, $eventId);
    }

    #[Override]
    public function importKey(string $purpose, string $key, ImportContext $context): ?string
    {
        if ($key === BallotLocationChoice::CANDIDATE_UNDECIDED) {
            return $key;
        }

        $locationId = $context->resolveRef(Location::class, $key)?->getId();

        return $locationId === null ? null : (string) $locationId;
    }
}
