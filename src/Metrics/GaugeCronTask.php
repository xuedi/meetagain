<?php declare(strict_types=1);

namespace App\Metrics;

use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class GaugeCronTask implements TaskInterface
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

    public function getCadence(): Cadence
    {
        return new Cadence('* * * * *');
    }

    public function getDescription(): string
    {
        return 'Takes a snapshot of runtime health for the metrics dashboard. Every gauge - database, Valkey, the container, log files, the email queue and any gauge a plugin adds - is read once and its points are sent to the metrics server. Without a METRICS_DSN it does nothing and reports disabled. A gauge that fails is named in the run message and turns the run into a warning, while the other gauges still report.';
    }

    public function run(): Result
    {
        if (!$this->recorder->isEnabled()) {
            return new Result(Status::ok, 'disabled');
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

        return new Result($failed === [] ? Status::ok : Status::warning, $message);
    }
}
