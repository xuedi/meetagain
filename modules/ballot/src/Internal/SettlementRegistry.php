<?php declare(strict_types=1);

namespace Module\Ballot\Internal;

use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\SettlementListenerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class SettlementRegistry
{
    /**
     * @param iterable<SettlementListenerInterface> $listeners
     */
    public function __construct(
        #[AutowireIterator(SettlementListenerInterface::class)]
        private iterable $listeners,
    ) {}

    public function settled(BallotOutcome $outcome): void
    {
        $this->listenerFor($outcome->purpose)?->settled($outcome);
    }

    public function listenerFor(string $purpose): ?SettlementListenerInterface
    {
        foreach ($this->sorted() as $listener) {
            if ($listener->supports($purpose)) {
                return $listener;
            }
        }

        return null;
    }

    /**
     * @return list<SettlementListenerInterface>
     */
    private function sorted(): array
    {
        $listeners = array_values(iterator_to_array($this->listeners));
        usort($listeners, static fn(SettlementListenerInterface $a, SettlementListenerInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $listeners;
    }
}
