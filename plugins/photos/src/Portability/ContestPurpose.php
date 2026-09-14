<?php declare(strict_types=1);

namespace Plugin\Photos\Portability;

use App\Portability\Ballot\PurposeInterface;
use App\Portability\ImportContext;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Override;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;

readonly class ContestPurpose implements PurposeInterface
{
    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === ContestService::PURPOSE;
    }

    #[Override]
    public function inScope(string $purpose, ?BallotSubject $subject, array $candidateKeys, Scope $scope): bool
    {
        $photoIds = $scope->itemIds[PhotoService::ITEM_TYPE] ?? [];

        return $subject === null && array_any($candidateKeys, static fn(string $key): bool => in_array((int) $key, $photoIds, true));
    }

    #[Override]
    public function importSubject(BallotSubject $subject, ImportContext $context): ?BallotSubject
    {
        return null;
    }

    #[Override]
    public function importKey(string $purpose, string $key, ImportContext $context): ?string
    {
        $photoId = $context->resolveItem(PhotoService::ITEM_TYPE, $key);

        return $photoId === null ? null : (string) $photoId;
    }
}
