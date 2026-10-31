<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Functional\Change;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\FieldResolution;
use Module\Proposal\Internal\Change\FieldBallotService;
use Module\Proposal\Tests\Stub\Change\Target;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Module\Members;

final class FieldBallotTest extends KernelTestCase
{
    private const string FIELD = 'name';

    public function testTallyingWritesNothingAndConfirmingAppliesTheWinnerAndDeniesTheLoser(): void
    {
        // Arrange
        self::bootKernel();
        $first = $this->members()->member('First');
        $second = $this->members()->member('Second');
        $steward = $this->members()->member('Steward');
        $this->target()->reviewerIds = [(int) $steward->getId()];
        $loser = $this->propose($first, 'Losing Name');
        $winner = $this->propose($second, 'Winning Name');
        $ballotId = $this->fieldBallots()->open(Target::TYPE, 7, self::FIELD, $steward);
        $winningKey = $this->keyLabelled($ballotId, $steward, 'Winning Name');
        $this->ballots()->cast($ballotId, (int) $first->getId(), [$winningKey]);
        $this->ballots()->cast($ballotId, (int) $second->getId(), [$winningKey]);
        $this->closeTheDeadline($ballotId);

        // Act
        $this->ballots()->tally($ballotId);
        $afterTally = $this->target()->applied;
        $this->fieldBallots()->confirm($ballotId, $steward);

        // Assert
        self::assertSame([], $afterTally, 'the tally alone writes nothing');
        self::assertSame([['targetId' => 7, 'field' => self::FIELD, 'value' => 'Winning Name']], $this->target()->applied);
        self::assertSame(FieldResolution::Applied, $this->changes()->find($winner)?->changes[0]->resolution);
        self::assertSame(FieldResolution::Denied, $this->changes()->find($loser)?->changes[0]->resolution);
    }

    public function testTheCandidatesAreTheDistinctValuesPlusLeavingItAlone(): void
    {
        // Arrange
        self::bootKernel();
        $steward = $this->members()->member('Steward');
        $this->propose($this->members()->member('First'), 'Same Name');
        $this->propose($this->members()->member('Second'), 'Same Name');
        $this->propose($this->members()->member('Third'), 'Other Name');

        // Act
        $ballotId = $this->fieldBallots()->open(Target::TYPE, 7, self::FIELD, $steward);

        // Assert
        self::assertCount(3, $this->ballots()->view($ballotId, (int) $steward->getId())->candidates ?? []);
    }

    private function propose(User $proposer, string $value): int
    {
        $id = $this->changes()->propose(Target::TYPE, 7, (int) $proposer->getId(), [new FieldChange(self::FIELD, 'Cafe', $value)]);
        self::assertNotNull($id);

        return $id;
    }

    private function keyLabelled(int $ballotId, User $viewer, string $label): string
    {
        foreach ($this->ballots()->view($ballotId, (int) $viewer->getId())->candidates ?? [] as $candidate) {
            if ($candidate->label === $label) {
                return $candidate->key;
            }
        }

        self::fail('No candidate labelled ' . $label);
    }

    private function closeTheDeadline(int $ballotId): void
    {
        $this->em()->getConnection()->executeStatement('UPDATE mod_ballot SET deadline = :past WHERE id = :id', [
            'past' => new DateTimeImmutable('-1 hour')->format('Y-m-d H:i:s'),
            'id' => $ballotId,
        ]);
        $this->em()->clear();
    }

    private function changes(): ChangeInterface
    {
        return self::getContainer()->get(ChangeInterface::class);
    }

    private function fieldBallots(): FieldBallotService
    {
        return self::getContainer()->get(FieldBallotService::class);
    }

    private function ballots(): BallotInterface
    {
        return self::getContainer()->get(BallotInterface::class);
    }

    private function target(): Target
    {
        return self::getContainer()->get(Target::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function members(): Members
    {
        return new Members($this->em());
    }
}
