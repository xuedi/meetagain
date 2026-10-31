<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Change;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\ElectorateProviderInterface;
use Module\Proposal\Contract\Change\ChangeInterface;
use Override;

final readonly class FieldBallotElectorate implements ElectorateProviderInterface
{
    public function __construct(
        private ChangeService $proposals,
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === ChangeInterface::BALLOT_PURPOSE;
    }

    #[Override]
    public function mayVote(int $userId, ?BallotSubject $subject): bool
    {
        if ($subject === null) {
            return false;
        }

        $user = $this->em->find(User::class, $userId);

        return $user instanceof User && $this->proposals->canProposeTarget($subject->type, $subject->id, $user);
    }
}
