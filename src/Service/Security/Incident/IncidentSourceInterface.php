<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One incident source feeds the IncidentAggregator from a single raw log table.
 * Implementations own their own watermark in app_state and produce IncidentMerger
 * calls that upsert into logs_incident keyed by (ip, open-window).
 */
#[AutoconfigureTag]
interface IncidentSourceInterface
{
    public function getKey(): string;

    public function ingest(): IncidentSourceStats;
}
