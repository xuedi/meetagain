<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\Status;
use Module\Task\Internal\Action\RequestState;
use Module\Task\Internal\Action\Runner;
use Module\Task\Internal\Entity\ActionRequest;
use Module\Task\Internal\Message\RunAction;
use Module\Task\Tests\Stub\Action;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ActionQueueTest extends KernelTestCase
{
    public function testQueueingWritesAPendingRequestAndRingsTheDoorbell(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $this->queue()->queue(Action::QUICK, [], 'admin@example.org');

        // Assert
        $requests = $this->entityManager()->getRepository(ActionRequest::class)->findAll();
        self::assertCount(1, $requests);
        self::assertSame(Action::QUICK, $requests[0]->getIdentifier());
        self::assertSame('admin@example.org', $requests[0]->getRequestedBy());
        self::assertSame(RequestState::Pending, $requests[0]->getState());
        $sent = $this->sent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(RunAction::class, $sent[0]);
    }

    public function testAnUnknownActionIsRefused(): void
    {
        // Arrange
        self::bootKernel();

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->queue()->queue('no-such-action', [], null);
    }

    public function testAnActionNeverRequestedHasNoLatestRequest(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $latest = $this->queue()->latest(Action::QUICK);

        // Assert
        self::assertNull($latest);
    }

    public function testTheLatestRequestIsTheNewestAndReportsItsOutcome(): void
    {
        // Arrange
        self::bootKernel();
        $this->queue()->queue(Action::QUICK, [], null);
        $this->queue()->queue(Action::QUICK, [], null);
        $pending = $this->queue()->latest(Action::QUICK);
        self::assertNotNull($pending);

        // Act
        $this->runner()->handle($pending->id);
        $finished = $this->queue()->latest(Action::QUICK);

        // Assert
        self::assertFalse($pending->isFinished());
        self::assertFalse($pending->isRunning());
        self::assertNotNull($finished);
        self::assertSame($pending->id, $finished->id);
        self::assertTrue($finished->isFinished());
        self::assertSame(Status::ok, $finished->status);
    }

    private function queue(): ActionQueueInterface
    {
        $queue = self::getContainer()->get(ActionQueueInterface::class);
        self::assertInstanceOf(ActionQueueInterface::class, $queue);

        return $queue;
    }

    private function runner(): Runner
    {
        $runner = self::getContainer()->get(Runner::class);
        self::assertInstanceOf(Runner::class, $runner);

        return $runner;
    }

    /**
     * @return list<object>
     */
    private function sent(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_map(static fn(Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
