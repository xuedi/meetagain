<?php declare(strict_types=1);

namespace App\Portability\Ballot;

use App\Portability\ImportContext;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Knows what the subject and the candidate keys of one ballot purpose point at: whether a ballot
 * belongs to an export scope, and which ids they become on the importing instance.
 */
#[AutoconfigureTag]
interface PurposeInterface
{
    public function supports(string $purpose): bool;

    /**
     * @param list<string> $candidateKeys
     */
    public function inScope(string $purpose, ?BallotSubject $subject, array $candidateKeys, Scope $scope): bool;

    public function importSubject(BallotSubject $subject, ImportContext $context): ?BallotSubject;

    public function importKey(string $purpose, string $key, ImportContext $context): ?string;
}
