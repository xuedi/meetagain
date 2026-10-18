<?php declare(strict_types=1);

namespace App\Command;

use App\DataHotfix\DataHotfixInterface;
use App\DataHotfix\DeferredException;
use App\Service\AppStateService;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

#[AsCommand(name: 'app:data-hotfix:run', description: 'Runs every data hotfix that has not run on this database yet; a deferred one runs again next time')]
class DataHotfixRunCommand extends Command
{
    private const string KEY_PREFIX = 'data_hotfix.';

    /**
     * @param iterable<DataHotfixInterface> $hotfixes
     */
    public function __construct(
        #[AutowireIterator(DataHotfixInterface::class)]
        private readonly iterable $hotfixes,
        private readonly AppStateService $appState,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sorted = is_array($this->hotfixes) ? $this->hotfixes : iterator_to_array($this->hotfixes);
        usort($sorted, static fn(DataHotfixInterface $a, DataHotfixInterface $b) => strcmp($a->getIdentifier(), $b->getIdentifier()));
        $pending = array_values(array_filter(
            $sorted,
            fn(DataHotfixInterface $hotfix): bool => $this->appState->get(self::KEY_PREFIX . $hotfix->getIdentifier()) === null,
        ));
        $skipped = count($sorted) - count($pending);

        $ran = 0;
        do {
            $before = $ran;
            $retry = [];
            $errors = [];
            foreach ($pending as $hotfix) {
                try {
                    $hotfix->execute();
                    $this->appState->set(self::KEY_PREFIX . $hotfix->getIdentifier(), $this->clock->now()->format(DATE_ATOM));
                    $ran++;
                    $output->writeln(sprintf('  data hotfix ran: %s', $hotfix->getIdentifier()));
                } catch (Throwable $e) {
                    $retry[] = $hotfix;
                    $errors[$hotfix->getIdentifier()] = $e;
                }
            }
            $pending = $retry;
        } while ($pending !== [] && $ran > $before);

        $failures = 0;
        foreach ($errors as $identifier => $e) {
            $deferred = $e instanceof DeferredException;
            $failures += $deferred ? 0 : 1;
            $output->writeln(sprintf('  data hotfix %s: %s: %s', $deferred ? 'deferred' : 'failed', $identifier, $e->getMessage()));
            $this->logger->log($deferred ? LogLevel::INFO : LogLevel::ERROR, $deferred ? 'Data hotfix deferred' : 'Data hotfix failed', [
                'identifier' => $identifier,
                'exception' => $e,
            ]);
        }

        $output->writeln(sprintf('hotfixes: %d ran, %d skipped, %d deferred, %d failed', $ran, $skipped, count($errors) - $failures, $failures));

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
