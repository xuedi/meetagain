<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Functional\Change;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\FieldResolution;
use Module\Proposal\Contract\Change\PortableChange;
use Module\Proposal\Contract\Status;
use Module\Proposal\Internal\Change\ChangeService;
use Module\Proposal\Internal\ChangeException;
use Module\Proposal\Internal\Entity\Change;
use Module\Proposal\Tests\Stub\Change\FamilyTarget;
use Module\Proposal\Tests\Stub\Change\Target;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Module\Members;

final class LifecycleTest extends KernelTestCase
{
    public function testUnchangedFieldsAreDroppedAndAnEmptyProposalIsNotStored(): void
    {
        // Arrange
        self::bootKernel();
        $proposerId = (int) $this->members()->member('Proposer')->getId();

        // Act
        $nothing = $this->changes()->propose(Target::TYPE, 7, $proposerId, [new FieldChange('name', 'Cafe', 'Cafe')]);
        $id = $this->changes()->propose(Target::TYPE, 7, $proposerId, [
            new FieldChange('name', 'Cafe', 'Cafe Central'),
            new FieldChange('street', 'Main 1', 'Main 1'),
        ]);

        // Assert
        self::assertNull($nothing);
        self::assertNotNull($id);
        $pending = $this->changes()->pendingFor(Target::TYPE, 7);
        self::assertCount(1, $pending);
        self::assertSame($proposerId, $pending[0]->proposerId);
        self::assertSame(Status::Pending, $pending[0]->status);
        self::assertSame(['name'], array_map(static fn(FieldChange $change): string => $change->field, $pending[0]->changes));
        self::assertSame([['field' => 'name', 'label' => 'Name', 'before' => 'Cafe', 'after' => 'Cafe Central', 'resolution' => null]], $pending[0]->rows);
        self::assertSame([7], $this->changes()->pendingTargetIds(Target::TYPE));
        self::assertSame(1, $this->changes()->countPending(Target::TYPE));
        self::assertSame(1, $this->changes()->countPending(Target::TYPE, 7));
    }

    public function testApplyingOneFieldKeepsTheProposalPendingAndTheLastFieldFinalizesIt(): void
    {
        // Arrange
        self::bootKernel();
        $reviewer = $this->reviewer();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central'), new FieldChange('street', 'Main 1', 'Main 2')]);

        // Act
        $this->service()->applyField($this->stored($id), 'name', $reviewer);
        $afterFirst = $this->changes()->find($id)?->status;
        $this->service()->denyField($this->stored($id), 'street', $reviewer);

        // Assert
        self::assertSame(Status::Pending, $afterFirst);
        self::assertSame(Status::Approved, $this->changes()->find($id)?->status);
        self::assertSame([['targetId' => 7, 'field' => 'name', 'value' => 'Cafe Central']], $this->target()->applied);
    }

    public function testDenyingEveryFieldRejectsTheProposal(): void
    {
        // Arrange
        self::bootKernel();
        $reviewer = $this->reviewer();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central')]);

        // Act
        $this->service()->denyField($this->stored($id), 'name', $reviewer);

        // Assert
        self::assertSame(Status::Rejected, $this->changes()->find($id)?->status);
        self::assertSame(FieldResolution::Denied, $this->changes()->find($id)?->changes[0]->resolution);
        self::assertSame([], $this->target()->applied);
    }

    public function testApplyingValidatesAgainAndWritesNothingOnAVerdict(): void
    {
        // Arrange
        self::bootKernel();
        $reviewer = $this->reviewer();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central')]);
        $this->target()->verdict = 'name is taken';

        // Act
        $refused = null;
        try {
            $this->service()->applyField($this->stored($id), 'name', $reviewer);
        } catch (ChangeException $e) {
            $refused = $e->getMessage();
        }

        // Assert
        self::assertSame('name is taken', $refused);
        self::assertSame([], $this->target()->applied);
        self::assertSame(Status::Pending, $this->changes()->find($id)?->status);
    }

    public function testAForgedScopeKeyWritesNothing(): void
    {
        // Arrange
        self::bootKernel();
        $reviewer = $this->reviewer();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central')]);

        // Act
        $refused = false;
        try {
            $this->service()->applyField($this->stored($id), 'name', $reviewer, 'everything');
        } catch (ChangeException) {
            $refused = true;
        }

        // Assert
        self::assertTrue($refused);
        self::assertSame([], $this->target()->applied);
        self::assertSame([], $this->target()->spread);
    }

    public function testTheDeclaredScopeSpreadsTheValue(): void
    {
        // Arrange
        self::bootKernel();
        $reviewer = $this->reviewer();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central')]);

        // Act
        $this->service()->applyField($this->stored($id), 'name', $reviewer, Target::SCOPE);

        // Assert
        self::assertSame([['targetId' => 7, 'scope' => Target::SCOPE, 'field' => 'name', 'value' => 'Cafe Central']], $this->target()->spread);
    }

    public function testOnlyTheProposerMayWithdraw(): void
    {
        // Arrange
        self::bootKernel();
        $id = $this->propose([new FieldChange('name', 'Cafe', 'Cafe Central')]);
        $stranger = $this->members()->member('Stranger');

        // Act
        $refused = false;
        try {
            $this->service()->withdraw($this->stored($id), $stranger);
        } catch (AccessDeniedException) {
            $refused = true;
        }
        $proposer = $this->em()->find(User::class, $this->changes()->find($id)?->proposerId);
        self::assertInstanceOf(User::class, $proposer);
        $this->service()->withdraw($this->stored($id), $proposer);

        // Assert
        self::assertTrue($refused);
        self::assertSame(Status::Withdrawn, $this->changes()->find($id)?->status);
    }

    public function testAFamilyProviderServesEveryTypeOfItsFamily(): void
    {
        // Arrange
        self::bootKernel();
        $proposerId = (int) $this->members()->member('Proposer')->getId();
        $type = FamilyTarget::PREFIX . 'film';

        // Act
        $id = $this->changes()->propose($type, 3, $proposerId, [new FieldChange('label_en', 'Drama', 'Dramas')]);

        // Assert
        self::assertNotNull($id);
        self::assertSame($type, $this->changes()->find($id)?->targetType);
        self::assertSame('Label_en', $this->changes()->find($id)?->rows[0]['label']);
    }

    public function testRestoreAndExportRoundTripAPendingProposal(): void
    {
        // Arrange
        self::bootKernel();
        $proposerId = (int) $this->members()->member('Proposer')->getId();
        $createdAt = new DateTimeImmutable('2026-01-02 03:04:05');

        // Act
        $id = $this->changes()->restore(new PortableChange(Target::TYPE, 9, $proposerId, [new FieldChange('name', 'a', 'b')], $createdAt));
        $exported = $this->changes()->exportPending([Target::TYPE]);

        // Assert
        self::assertCount(1, $exported);
        self::assertSame($id, $exported[0]->id);
        self::assertSame(9, $exported[0]->targetId);
        self::assertSame($createdAt->format('c'), $exported[0]->createdAt->format('c'));
        self::assertSame([], $this->changes()->exportPending([]));
    }

    public function testRemovingATargetsProposalsLeavesOthersAlone(): void
    {
        // Arrange
        self::bootKernel();
        $proposerId = (int) $this->members()->member('Proposer')->getId();
        $this->changes()->propose(Target::TYPE, 7, $proposerId, [new FieldChange('name', 'a', 'b')]);
        $this->changes()->propose(Target::TYPE, 8, $proposerId, [new FieldChange('name', 'a', 'c')]);

        // Act
        $this->changes()->removeForTarget(Target::TYPE, 7);

        // Assert
        self::assertSame([8], $this->changes()->pendingTargetIds(Target::TYPE));
    }

    /**
     * @param list<FieldChange> $changes
     */
    private function propose(array $changes): int
    {
        $id = $this->changes()->propose(Target::TYPE, 7, (int) $this->members()->member('Proposer')->getId(), $changes);
        self::assertNotNull($id);

        return $id;
    }

    private function reviewer(): User
    {
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];

        return $reviewer;
    }

    private function stored(int $id): Change
    {
        $proposal = $this->service()->get($id);
        self::assertInstanceOf(Change::class, $proposal);

        return $proposal;
    }

    private function changes(): ChangeInterface
    {
        return self::getContainer()->get(ChangeInterface::class);
    }

    private function service(): ChangeService
    {
        return self::getContainer()->get(ChangeService::class);
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
