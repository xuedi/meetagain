<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Suggestion\Contract\PortableSuggestion;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Contract\SuggestionInterface;
use Module\Suggestion\Internal\Emails\Approved;
use Module\Suggestion\Internal\Emails\Rejected;
use Module\Suggestion\Internal\Entity\Suggestion;
use Module\Suggestion\Internal\SuggestionException;
use Module\Suggestion\Internal\SuggestionService;
use Module\Suggestion\Tests\Stub\Draft;
use Module\Suggestion\Tests\Stub\InactivePluginTarget;
use Module\Suggestion\Tests\Stub\Scope;
use Module\Suggestion\Tests\Stub\Target;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Module\Members;

final class LifecycleTest extends KernelTestCase
{
    public function testAProposalWaitsInTheProposersPendingList(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');

        // Act
        $error = $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));

        // Assert
        self::assertNull($error);
        $pending = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE);
        self::assertCount(1, $pending);
        self::assertSame(Status::Pending, $pending[0]->status);
        self::assertSame('Stub Cafe', $pending[0]->description);
        self::assertSame([['label' => 'Name', 'value' => 'Cafe']], $pending[0]->rows);
    }

    public function testARefusedDraftReturnsTheVerdictAndStoresNothing(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $this->target()->verdict = 'already exists';

        // Act
        $error = $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));

        // Assert
        self::assertSame('already exists', $error);
        self::assertSame([], $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE));
    }

    public function testApprovalCreatesTheRowForTheProposerAndRecordsItsId(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;
        $service = $this->service();

        // Act
        $createdId = $service->approve($this->stored($id), $this->draft('Cafe Central'), $reviewer);

        // Assert
        self::assertSame([['name' => 'Cafe Central', 'proposerId' => (int) $proposer->getId(), 'scope' => null]], $this->target()->created);
        $view = $this->suggestions()->find($id);
        self::assertSame(Status::Approved, $view?->status);
        self::assertSame($createdId, $view->createdId);
        self::assertSame('Stub Cafe Central', $view->description);
    }

    public function testApprovalValidatesAgainBeforeCreating(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;
        $this->target()->verdict = 'a duplicate appeared meanwhile';
        $service = $this->service();

        // Act
        try {
            $service->approve($this->stored($id), $this->draft('Cafe'), $reviewer);
            self::fail('Approval went through despite the verdict');
        } catch (SuggestionException $e) {
            self::assertSame('a duplicate appeared meanwhile', $e->getMessage());
        }

        // Assert
        self::assertSame([], $this->target()->created);
        self::assertSame(Status::Pending, $this->suggestions()->find($id)?->status);
    }

    public function testASuggestionOfAnInactivePluginIsInvisible(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');

        // Act
        $id = $this->suggestions()->restore(new PortableSuggestion(InactivePluginTarget::TYPE, (int) $proposer->getId(), ['name' => 'Cafe']));

        // Assert
        self::assertNull($this->suggestions()->providerFor(InactivePluginTarget::TYPE));
        self::assertNull($this->suggestions()->find($id));
        self::assertSame([], $this->suggestions()->pendingFor((int) $proposer->getId(), InactivePluginTarget::TYPE));
    }

    public function testRestoreStoresAPendingSuggestionAsGiven(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');

        // Act
        $id = $this->suggestions()->restore(new PortableSuggestion(Target::TYPE, (int) $proposer->getId(), ['name' => 'Seeded']));

        // Assert
        $view = $this->suggestions()->find($id);
        self::assertSame(Status::Pending, $view?->status);
        self::assertSame('Stub Seeded', $view->description);
        self::assertNull($view->createdId);
    }

    public function testAScopedSuggestionIsReviewableOnlyByReviewersOfItsScope(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $insider = $this->members()->member('Insider');
        $outsider = $this->members()->member('Outsider');
        $this->target()->reviewerIds = [(int) $insider->getId(), (int) $outsider->getId()];
        $this->target()->reviewerScopes = [(int) $insider->getId() => 'here', (int) $outsider->getId() => 'elsewhere'];
        $this->scope()->current = 'here';
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $this->scope()->current = null;
        $service = $this->service();

        // Act
        $insiderSees = $service->pendingReviewableBy($insider);
        $outsiderSees = $service->pendingReviewableBy($outsider);

        // Assert
        self::assertCount(1, $insiderSees);
        self::assertSame('here', $insiderSees[0]->getScope());
        self::assertSame([], $outsiderSees);
    }

    public function testApprovingAScopedSuggestionCreatesTheRowInItsScope(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];
        $this->target()->reviewerScopes = [(int) $reviewer->getId() => 'here'];
        $this->scope()->current = 'here';
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;
        $this->scope()->current = null;

        // Act
        $this->service()->approve($this->stored($id), $this->draft('Cafe'), $reviewer);

        // Assert
        self::assertSame('here', $this->target()->created[0]['scope']);
        self::assertNull($this->scope()->current);
    }

    public function testRejectingAScopedSuggestionIsDeniedOutsideItsScope(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $outsider = $this->members()->member('Outsider');
        $this->target()->reviewerIds = [(int) $outsider->getId()];
        $this->target()->reviewerScopes = [(int) $outsider->getId() => 'elsewhere'];
        $this->scope()->current = 'here';
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;
        $this->scope()->current = null;

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $this->service()->reject($this->stored($id), $outsider);
    }

    public function testApprovalMailsTheProposer(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;

        // Act
        $this->service()->approve($this->stored($id), $this->draft('Cafe'), $reviewer);

        // Assert
        $mails = $this->sendlog()->list(recipient: (string) $proposer->getEmail(), template: Approved::IDENTIFIER);
        self::assertCount(1, $mails);
        self::assertSame('Stub Cafe', $mails[0]->context['description'] ?? null);
    }

    public function testRejectionMailsTheProposer(): void
    {
        // Arrange
        self::bootKernel();
        $proposer = $this->members()->member('Proposer');
        $reviewer = $this->members()->member('Reviewer');
        $this->target()->reviewerIds = [(int) $reviewer->getId()];
        $this->suggestions()->propose(Target::TYPE, (int) $proposer->getId(), $this->draft('Cafe'));
        $id = $this->suggestions()->pendingFor((int) $proposer->getId(), Target::TYPE)[0]->id;

        // Act
        $this->service()->reject($this->stored($id), $reviewer);

        // Assert
        self::assertCount(1, $this->sendlog()->list(recipient: (string) $proposer->getEmail(), template: Rejected::IDENTIFIER));
        self::assertSame([], $this->sendlog()->list(recipient: (string) $proposer->getEmail(), template: Approved::IDENTIFIER));
    }

    private function draft(string $name): Draft
    {
        $draft = new Draft();
        $draft->name = $name;

        return $draft;
    }

    private function stored(int $id): Suggestion
    {
        $suggestion = $this->service()->get($id);
        if ($suggestion === null) {
            self::fail('Suggestion ' . $id . ' was not stored');
        }

        return $suggestion;
    }

    private function suggestions(): SuggestionInterface
    {
        return self::getContainer()->get(SuggestionInterface::class);
    }

    private function service(): SuggestionService
    {
        return self::getContainer()->get(SuggestionService::class);
    }

    private function sendlog(): SendlogInterface
    {
        return self::getContainer()->get(SendlogInterface::class);
    }

    private function scope(): Scope
    {
        return self::getContainer()->get(Scope::class);
    }

    private function target(): Target
    {
        return self::getContainer()->get(Target::class);
    }

    private function members(): Members
    {
        return new Members(self::getContainer()->get(EntityManagerInterface::class));
    }
}
