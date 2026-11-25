<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\Entity\Event;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\EventRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Override;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Repository\PublicationRepository;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Clock\ClockInterface;

readonly class Reconciler implements TaskInterface
{
    public function __construct(
        private EventRepository $events,
        private PublicationRepository $publications,
        private PublishQueue $publishQueue,
        private AccountRepository $accounts,
        private AccountService $accountService,
        private Publisher $publisher,
        private ConfigService $configService,
        private Resolver $resolver,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'fediverse-reconcile';
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('15 4 * * *', jitterSeconds: 1800, offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf(
            'Brings the fediverse in line with the events once a day. An account whose scope no longer runs the plugin, or no longer exists, has its events deleted and is retired. Then it queues every public event that starts within the next %d days, so an event enters the fediverse as it comes within reach and a scope that just switched the plugin on gets its upcoming events, and every published event that has not started yet, so one that stopped qualifying without being edited - a scope gone private - is withdrawn.',
            Publisher::HORIZON_DAYS,
        );
    }

    #[Override]
    public function run(): Result
    {
        $this->accountService->ensureForScope(null);
        $retired = 0;
        foreach ($this->accounts->findLive() as $account) {
            if (!$this->accountService->isFederating($account)) {
                $this->publisher->leave($account);
                ++$retired;
            }
        }

        $now = $this->clock->now();
        $upcoming = array_map(
            static fn(Event $event): int => (int) $event->getId(),
            $this->events->findUpcomingEventsWithinRange($now, $now->modify(sprintf('+%d days', Publisher::HORIZON_DAYS))),
        );
        $published = $this->publications->findLiveEventIdsStartingFrom($now);
        $eventIds = array_values(array_unique([...$upcoming, ...$published]));
        if ($eventIds !== []) {
            $this->publishQueue->queue($eventIds);
        }

        return new Result(Status::ok, sprintf('Queued: %d, accounts retired: %d', count($eventIds), $retired));
    }

    public function stop(): int
    {
        $withdrawn = 0;
        foreach ($this->accounts->findLive() as $account) {
            $withdrawn += $this->publisher->leave($account);
            $this->forgetUsername($account->getScopeId());
        }
        $this->forgetUsername(null);
        $this->configService->reset();

        return $withdrawn;
    }

    private function forgetUsername(?string $scopeId): void
    {
        $store = $this->resolver->resolveStore(ConfigService::KEY, $scopeId);
        $config = $store?->load(ConfigService::KEY, $scopeId);
        if ($config instanceof Config && $config->getUsername() !== null) {
            $store->save(ConfigService::KEY, $config->setUsername(null), $scopeId);
        }
    }
}
