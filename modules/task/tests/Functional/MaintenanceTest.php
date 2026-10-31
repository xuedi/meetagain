<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\Status;
use Module\Task\Internal\Action\Drain;
use Module\Task\Internal\Action\Requests;
use Module\Task\Internal\Action\RequestState;
use Module\Task\Internal\Action\Runner;
use Module\Task\Internal\Action\Settings;
use Module\Task\Internal\Entity\ActionRequest;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Message\RunAction;
use Module\Task\Internal\Repository\RunRepository;
use Module\Task\Tests\Stub\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\Module\Members;

final class MaintenanceTest extends WebTestCase
{
    private const string BASE = '/en/admin/system/maintenance';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    #[DataProvider('providePages')]
    public function testAGuestIsSentAwayFromEveryPage(string $path): void
    {
        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseRedirects();
    }

    #[DataProvider('providePages')]
    public function testAMemberWhoIsNoAdminIsRefusedEveryPage(string $path): void
    {
        // Arrange
        $this->client->loginUser($this->members()->member('Member'));

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('providePages')]
    public function testEveryPageRendersForAnAdmin(string $path): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePages(): iterable
    {
        yield 'list' => [''];
        yield 'an action with parameters' => ['/' . Action::IDENTIFIER];
        yield 'an action without parameters' => ['/' . Action::QUICK];
    }

    public function testSubmittingTheRunFormWritesARequestAndRingsTheDoorbell(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $form = $this->client
            ->request('GET', self::BASE . '/' . Action::IDENTIFIER)
            ->filter('form[name="run"]')
            ->form();
        $form['run[mode]'] = 'thorough';
        $form['run[limit]'] = '9';
        $form['run[dry]']->tick();

        // Act
        $this->client->submit($form);

        // Assert
        self::assertResponseRedirects(self::BASE . '/' . Action::IDENTIFIER);
        $request = $this->onlyRequest();
        self::assertSame(RequestState::Pending, $request->getState());
        self::assertSame(['mode' => 'thorough', 'path' => '/srv/default', 'limit' => 9, 'dry' => true], $request->getValues());
        self::assertSame('admin@module-test.example', $request->getRequestedBy());
        self::assertEquals([new RunAction((int) $request->getId())], $this->sent());
    }

    public function testAnActionWithoutParametersRunsFromItsRow(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $run = $this->client->request('GET', self::BASE)->filter('a[href$="/' . Action::QUICK . '/run"]');

        // Act
        $this->client->request('POST', (string) $run->attr('href'), ['_token' => $run->attr('data-csrf-token')]);

        // Assert
        self::assertResponseRedirects(self::BASE);
        self::assertSame(Action::QUICK, $this->onlyRequest()->getIdentifier());
    }

    public function testARunWithoutAValidTokenIsRefused(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $this->client->request('POST', self::BASE . '/' . Action::QUICK . '/run', ['_token' => 'forged']);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->requestCount());
    }

    public function testATokenForOneActionDoesNotRunAnother(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $form = $this->client
            ->request('GET', self::BASE . '/' . Action::IDENTIFIER)
            ->filter('form[name="run"]')
            ->form();

        // Act
        $this->client->request('POST', self::BASE . '/' . Action::QUICK . '/run', ['_token' => $form['run[_token]']->getValue()]);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testTheDoorbellRunsTheActionOnceAndLogsTheRun(): void
    {
        // Arrange
        $request = $this->submit(['mode' => 'fast']);

        // Act
        $this->runner()->handle((int) $request->getId());
        $this->runner()->handle((int) $request->getId());

        // Assert
        self::assertSame(1, $this->stub()->runs);
        self::assertSame(['mode' => 'fast'], $this->stub()->lastValues);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
        self::assertSame(Status::ok, $request->getStatus());
        $logged = self::getContainer()->get(RunRepository::class)->findLatestPerIdentifier()[Action::IDENTIFIER] ?? null;
        self::assertSame('run 1', $logged?->getMessage());
    }

    public function testASecondRequestWaitsWhileAnotherOfTheSameActionRuns(): void
    {
        // Arrange
        $request = $this->submit([]);
        $lock = $this->locks()->createLock(Runner::LOCK_PREFIX . Action::IDENTIFIER);
        $lock->acquire();

        // Act
        try {
            $this->runner()->handle((int) $request->getId());
        } finally {
            $lock->release();
        }

        // Assert
        self::assertSame(0, $this->stub()->runs);
        $request = $this->reload($request);
        self::assertSame(RequestState::Pending, $request->getState());
    }

    public function testTheDrainRunsARequestWhoseDoorbellWasLost(): void
    {
        // Arrange
        $request = $this->submit([]);
        $this->backdate($request, 'created_at', '-10 minutes');

        // Act
        $this->drain()->run();

        // Assert
        self::assertSame(1, $this->stub()->runs);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
    }

    public function testTheDrainLeavesAFreshRequestToItsDoorbell(): void
    {
        // Arrange
        $request = $this->submit([]);

        // Act
        $this->drain()->run();

        // Assert
        self::assertSame(0, $this->stub()->runs);
        $request = $this->reload($request);
        self::assertSame(RequestState::Pending, $request->getState());
    }

    public function testAnExpiredLeaseIsMarkedFailedAndNotRunAgain(): void
    {
        // Arrange
        $request = $this->submit([]);
        $request->start(new DateTimeImmutable('-2 hours'), new DateTimeImmutable('-1 hour'));
        $this->entityManager()->flush();

        // Act
        $result = $this->drain()->run();

        // Assert
        self::assertSame(Status::warning, $result->status);
        self::assertSame(0, $this->stub()->runs);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
        self::assertSame(Status::error, $request->getStatus());
        $logged = self::getContainer()->get(RunRepository::class)->findLatestPerIdentifier()[Action::IDENTIFIER] ?? null;
        self::assertSame(Status::error, $logged?->getStatus());
    }

    public function testAContinuingStepPutsTheRequestBackWithItsCursorAndRingsAgain(): void
    {
        // Arrange
        $request = $this->submit([], $this->steps());

        // Act
        $this->runner()->handle((int) $request->getId());

        // Assert
        $request = $this->reload($request);
        self::assertSame(RequestState::Pending, $request->getState());
        self::assertSame('1', $request->getCursor());
        self::assertSame('step 1 of 3', $request->getMessage());
        self::assertNull($request->getLeaseUntil());
        self::assertNotNull($request->getStartedAt());
        self::assertEquals([new RunAction((int) $request->getId()), new RunAction((int) $request->getId())], $this->sent());
        self::assertSame(0, $this->runCount(Action::STEPS));
    }

    public function testEveryDoorbellRunsTheNextStepAndTheRunIsLoggedOnceAtTheEnd(): void
    {
        // Arrange
        $request = $this->submit([], $this->steps());

        // Act
        for ($i = 0; $i < (Action::STEP_COUNT + 1); $i++) {
            $this->runner()->handle((int) $request->getId());
        }

        // Assert
        self::assertSame([null, '1', '2'], $this->steps()->cursors);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
        self::assertSame(Status::ok, $request->getStatus());
        self::assertSame('all 3 steps', $request->getMessage());
        self::assertSame(1, $this->runCount(Action::STEPS));
        self::assertSame(1, $this->entityManager()->getRepository(ActionRequest::class)->count(['identifier' => Action::STEPS]));
    }

    public function testASecondRequestWaitsUntilTheFirstHasWalkedAllItsSteps(): void
    {
        // Arrange
        $first = $this->submit([], $this->steps());
        $second = $this->submit([], $this->steps());
        $this->runner()->handle((int) $first->getId());

        // Act
        $this->runner()->handle((int) $second->getId());

        // Assert
        self::assertSame([null], $this->steps()->cursors);
        self::assertSame(RequestState::Pending, $this->reload($second)->getState());
        self::assertNull($this->reload($second)->getStartedAt());
    }

    public function testARequestWhoseLeaseRanOutHoldsNobodyBack(): void
    {
        // Arrange
        $dead = $this->submit([], $this->steps());
        $dead->start(new DateTimeImmutable('-2 hours'), new DateTimeImmutable('-1 hour'));
        $this->entityManager()->flush();
        $fresh = $this->submit([], $this->steps());

        // Act
        $this->runner()->handle((int) $fresh->getId());

        // Assert
        self::assertSame([null], $this->steps()->cursors);
        self::assertSame('1', $this->reload($fresh)->getCursor());
    }

    public function testAFailingMiddleStepFinishesTheRequest(): void
    {
        // Arrange
        $this->steps()->failAtStep = 2;
        $request = $this->submit([], $this->steps());

        // Act
        for ($i = 0; $i < Action::STEP_COUNT; $i++) {
            $this->runner()->handle((int) $request->getId());
        }

        // Assert
        self::assertSame([null, '1'], $this->steps()->cursors);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
        self::assertSame(Status::exception, $request->getStatus());
        self::assertSame('step 2 broke', $request->getMessage());
        self::assertSame(1, $this->runCount(Action::STEPS));
    }

    public function testTheDrainRunsTheNextStepWhenTheDoorbellOfAContinuingRequestWasLost(): void
    {
        // Arrange
        $request = $this->submit([], $this->steps());
        $this->runner()->handle((int) $request->getId());
        $this->backdate($request, 'created_at', '-10 minutes');

        // Act
        $this->drain()->run();

        // Assert
        self::assertSame([null, '1'], $this->steps()->cursors);
        $request = $this->reload($request);
        self::assertSame('2', $request->getCursor());
    }

    public function testALeaseThatExpiresMidStepFailsTheRequestAndDropsTheChain(): void
    {
        // Arrange
        $request = $this->submit([], $this->steps());
        $this->runner()->handle((int) $request->getId());
        $request = $this->reload($request);
        $request->start(new DateTimeImmutable('-2 hours'), new DateTimeImmutable('-1 hour'));
        $this->entityManager()->flush();

        // Act
        $this->drain()->run();
        $this->runner()->handle((int) $request->getId());

        // Assert
        self::assertSame([null], $this->steps()->cursors);
        $request = $this->reload($request);
        self::assertSame(RequestState::Finished, $request->getState());
        self::assertSame(Status::error, $request->getStatus());
    }

    public function testSavedSettingsPrefillTheNextRun(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $form = $this->client
            ->request('GET', self::BASE . '/' . Action::IDENTIFIER)
            ->filter('form[name="settings"]')
            ->form();
        $form['settings[path]'] = '/srv/mirror';

        // Act
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        // Assert
        self::assertSame('/srv/mirror', $crawler->filter('input[name="run[path]"]')->attr('value'));
        self::assertSame('/srv/mirror', $this->settings()->get(Action::IDENTIFIER, 'path'));
    }

    public function testTheRunsFilterListsActionsNextToTasks(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $crawler = $this->client->request('GET', '/en/admin/logs/tasks/runs?task=' . Action::IDENTIFIER);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Task: ' . Action::IDENTIFIER, $crawler->filter('body')->text());
    }

    /**
     * @param array<string, string|int|bool|null> $values
     */
    private function submit(array $values, ?Action $action = null): ActionRequest
    {
        $requests = self::getContainer()->get(Requests::class);
        self::assertInstanceOf(Requests::class, $requests);

        return $requests->submit($action ?? $this->stub(), $values, null);
    }

    private function reload(ActionRequest $request): ActionRequest
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(ActionRequest::class, $request->getId());
        self::assertInstanceOf(ActionRequest::class, $reloaded);

        return $reloaded;
    }

    private function steps(): Action
    {
        $stub = self::getContainer()->get('stub.step_action');
        self::assertInstanceOf(Action::class, $stub);

        return $stub;
    }

    private function runCount(string $identifier): int
    {
        return $this->entityManager()->getRepository(Run::class)->count(['identifier' => $identifier]);
    }

    private function backdate(ActionRequest $request, string $column, string $offset): void
    {
        self::getContainer()
            ->get(Connection::class)
            ->update('mod_task_action_request', [$column => new DateTimeImmutable($offset)->format('Y-m-d H:i:s')], ['id' => $request->getId()]);
    }

    private function onlyRequest(): ActionRequest
    {
        $requests = $this->entityManager()->getRepository(ActionRequest::class)->findAll();
        self::assertCount(1, $requests);

        return $requests[0];
    }

    private function requestCount(): int
    {
        return $this->entityManager()->getRepository(ActionRequest::class)->count();
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

    private function stub(): Action
    {
        $stub = self::getContainer()->get(Action::class);
        self::assertInstanceOf(Action::class, $stub);

        return $stub;
    }

    private function runner(): Runner
    {
        $runner = self::getContainer()->get(Runner::class);
        self::assertInstanceOf(Runner::class, $runner);

        return $runner;
    }

    private function drain(): Drain
    {
        $drain = self::getContainer()->get(Drain::class);
        self::assertInstanceOf(Drain::class, $drain);

        return $drain;
    }

    private function settings(): Settings
    {
        $settings = self::getContainer()->get(Settings::class);
        self::assertInstanceOf(Settings::class, $settings);

        return $settings;
    }

    private function locks(): LockFactory
    {
        $locks = self::getContainer()->get(LockFactory::class);
        self::assertInstanceOf(LockFactory::class, $locks);

        return $locks;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function members(): Members
    {
        return new Members($this->entityManager());
    }
}
