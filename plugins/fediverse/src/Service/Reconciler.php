<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\Entity\Event;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\EventRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Module\Task\Contract\TriggerInterface;
use Override;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Repository\PublicationRepository;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Clock\ClockInterface;

readonly class Reconciler implements TaskInterface
{
    public const string IDENTIFIER = 'fediverse-reconcile';

    public function __construct(
        private EventRepository $events,
        private PublicationRepository $publications,
        private PublishQueue $publishQueue,
        private AccountRepository $accounts,
        private FollowerRepository $followers,
        private AccountService $accountService,
        private PublicationService $publicationService,
        private ConfigService $configService,
        private Resolver $resolver,
        private TriggerInterface $trigger,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
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
            'Brings the fediverse in line with the events once a day. An account whose scope no longer runs the plugin, is no longer indexable, has no username or no longer exists has its events deleted and is retired, and followers on a blocked server are dropped. Then it queues every public event that starts within the next %d days, so an event enters the fediverse as it comes within reach and a scope that just switched the plugin on gets its upcoming events, and every published event that has not started yet, so one that stopped qualifying without being edited - a scope gone private - is withdrawn.',
            PublicationService::HORIZON_DAYS,
        );
    }

    #[Override]
    public function run(): Result
    {
        $this->accountService->ensureForScope(null);
        $retired = 0;
        foreach ($this->accounts->findLive() as $account) {
            if (!$this->accountService->isFederating($account)) {
                $this->publicationService->leave($account);
                ++$retired;
            }
        }

        $ceiling = $this->configService->getCeiling();
        $dropped = $this->followers->removeOnHosts($ceiling->blocks(...));

        $now = $this->clock->now();
        $upcoming = array_map(
            static fn(Event $event): int => (int) $event->getId(),
            $this->events->findUpcomingEventsWithinRange($now, $now->modify(sprintf('+%d days', PublicationService::HORIZON_DAYS))),
        );
        $published = $this->publications->findLiveEventIdsStartingFrom($now);
        $eventIds = array_values(array_unique([...$upcoming, ...$published]));
        if ($eventIds !== []) {
            $this->publishQueue->queue($eventIds);
        }

        return new Result(Status::ok, sprintf('Queued: %d, accounts retired: %d, blocked followers dropped: %d', count($eventIds), $retired, $dropped));
    }

    public function stop(): void
    {
        foreach ($this->accounts->findScopeIds() as $scopeId) {
            $this->forgetUsername($scopeId);
        }
        $this->forgetUsername(null);
        $this->configService->reset();
        $this->trigger->runSoon(self::IDENTIFIER);
    }

    private function forgetUsername(?string $scopeId): void
    {
        $store = $this->resolver->resolveStore(ConfigService::KEY, $scopeId);
        $config = $store?->load(ConfigService::KEY, $scopeId);
        if ($config instanceof Config && ($config->getUsername() !== null || $config->isActive())) {
            $store->save(ConfigService::KEY, $config->setUsername(null)->setActive(false), $scopeId);
        }
    }
}
