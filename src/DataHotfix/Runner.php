<?php declare(strict_types=1);

namespace App\DataHotfix;

use App\DependencyInjection\SeesInactivePlugins;
use App\Service\AppStateService;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[SeesInactivePlugins]
readonly class Runner
{
    private const string KEY_PREFIX = 'data_hotfix.';

    /**
     * @param iterable<DataHotfixInterface> $hotfixes
     */
    public function __construct(
        #[AutowireIterator(DataHotfixInterface::class)]
        private iterable $hotfixes,
        private AppStateService $appState,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<DataHotfixInterface>
     */
    public function all(): array
    {
        $sorted = is_array($this->hotfixes) ? array_values($this->hotfixes) : iterator_to_array($this->hotfixes, false);
        usort(
            $sorted,
            fn(DataHotfixInterface $a, DataHotfixInterface $b): int => [$this->date($a), $a->getIdentifier()] <=> [$this->date($b), $b->getIdentifier()],
        );

        return $sorted;
    }

    public function find(string $identifier): ?DataHotfixInterface
    {
        return array_find($this->all(), static fn(DataHotfixInterface $hotfix): bool => $hotfix->getIdentifier() === $identifier);
    }

    public function ranAt(DataHotfixInterface $hotfix): ?string
    {
        return $this->appState->get(self::KEY_PREFIX . $hotfix->getIdentifier());
    }

    public function run(DataHotfixInterface $hotfix): void
    {
        $hotfix->execute();
        $this->record($hotfix);
    }

    public function record(DataHotfixInterface $hotfix): void
    {
        $this->appState->set(self::KEY_PREFIX . $hotfix->getIdentifier(), $this->clock->now()->format(DATE_ATOM));
    }

    private function date(DataHotfixInterface $hotfix): string
    {
        return preg_match('/\d{4}_\d{2}_\d{2}/', $hotfix->getIdentifier(), $match) === 1 ? $match[0] : $hotfix->getIdentifier();
    }
}
