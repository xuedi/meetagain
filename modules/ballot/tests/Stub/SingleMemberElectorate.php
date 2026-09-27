<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Stub;

use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\ElectorateProviderInterface;
use Override;

class SingleMemberElectorate implements ElectorateProviderInterface
{
    public const string PURPOSE = 'test.restricted';

    public ?int $allowedUserId = null;

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === self::PURPOSE;
    }

    #[Override]
    public function mayVote(int $userId, ?BallotSubject $subject): bool
    {
        return $userId === $this->allowedUserId;
    }
}
