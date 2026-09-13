<?php declare(strict_types=1);

namespace App\Portability\Ballot\Purpose;

use App\Entity\Event;
use App\Item\Ballot\Purpose;
use App\Portability\Ballot\PurposeInterface;
use App\Portability\ImportContext;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Override;

readonly class ItemPurpose implements PurposeInterface
{
    #[Override]
    public function supports(string $purpose): bool
    {
        return Purpose::itemTypeOf($purpose) !== null;
    }

    #[Override]
    public function inScope(string $purpose, ?BallotSubject $subject, array $candidateKeys, Scope $scope): bool
    {
        return $subject !== null && $subject->type === Purpose::SUBJECT_TYPE && in_array($subject->id, $scope->eventIds, true);
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
        $itemType = Purpose::itemTypeOf($purpose);
        $itemId = $itemType === null ? null : $context->resolveItem($itemType, $key);

        return $itemId === null ? null : (string) $itemId;
    }
}
