<?php declare(strict_types=1);

namespace App\Metrics;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Collected on every cron tick while metrics are enabled. A gauge that throws is skipped for that tick.
 */
#[AutoconfigureTag]
interface GaugeInterface
{
    /**
     * @return iterable<Point>
     */
    public function collect(): iterable;
}
