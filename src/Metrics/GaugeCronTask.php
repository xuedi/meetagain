<?php declare(strict_types=1);

namespace App\Metrics;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class GaugeCronTask implements CronTaskInterface
{
    /**
     * @param iterable<GaugeInterface> $gauges
     */
    public function __construct(
        private Recorder $recorder,
        #[AutowireIterator(GaugeInterface::class)]
        private iterable $gauges,
    ) {}

    public function getIdentifier(): string
    {
        return 'metrics-gauges';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        if (!$this->recorder->isEnabled()) {
            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'disabled');
        }

        $points = 0;
        $failed = [];
        foreach ($this->gauges as $gauge) {
            try {
                foreach ($gauge->collect() as $point) {
                    $this->recorder->add($point);
                    $points++;
                }
            } catch (Throwable $exception) {
                $failed[] = sprintf('%s: %s', new ReflectionClass($gauge)->getShortName(), $exception->getMessage());
            }
        }

        $message = sprintf('%d points', $points);
        if ($failed !== []) {
            $message .= ', failed: ' . implode('; ', $failed);
        }
        $output->writeln('GaugeCronTask: ' . $message);

        return new CronTaskResult($this->getIdentifier(), $failed === [] ? CronTaskStatus::ok : CronTaskStatus::warning, $message);
    }
}
