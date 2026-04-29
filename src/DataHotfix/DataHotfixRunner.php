<?php declare(strict_types=1);

namespace App\DataHotfix;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Service\AppStateService;
use App\ValueObject\CronTaskResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

readonly class DataHotfixRunner implements CronTaskInterface
{
    private const string KEY_PREFIX = 'data_hotfix.';
    private const string IDENTIFIER = 'data-hotfix-runner';

    public function __construct(
        #[AutowireIterator(DataHotfixInterface::class)]
        private iterable $hotfixes,
        private AppStateService $appState,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $ran = 0;
        $skipped = 0;
        $failed = 0;
        $worst = CronTaskStatus::ok;

        $sorted = is_array($this->hotfixes) ? $this->hotfixes : iterator_to_array($this->hotfixes);
        usort($sorted, static fn(DataHotfixInterface $a, DataHotfixInterface $b) => strcmp($a->getIdentifier(), $b->getIdentifier()));

        foreach ($sorted as $hotfix) {
            $key = self::KEY_PREFIX . $hotfix->getIdentifier();
            if ($this->appState->get($key) !== null) {
                $skipped++;
                continue;
            }
            try {
                $hotfix->execute();
                $this->appState->set($key, $this->clock->now()->format(DATE_ATOM));
                $ran++;
                $output->writeln(sprintf('  data hotfix ran: %s', $hotfix->getIdentifier()));
            } catch (Throwable $e) {
                $failed++;
                $worst = $worst->worst(CronTaskStatus::error);
                $this->logger->error('Data hotfix failed', [
                    'identifier' => $hotfix->getIdentifier(),
                    'exception' => $e,
                ]);
            }
        }

        return new CronTaskResult(
            self::IDENTIFIER,
            $worst,
            sprintf('hotfixes: %d ran, %d skipped, %d failed', $ran, $skipped, $failed),
        );
    }
}
