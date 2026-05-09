<?php declare(strict_types=1);

namespace App\Enum;

enum IncidentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low      => 'admin_security.severity_low',
            self::Medium   => 'admin_security.severity_medium',
            self::High     => 'admin_security.severity_high',
            self::Critical => 'admin_security.severity_critical',
        };
    }

    public function tagClass(): string
    {
        return match ($this) {
            self::Low      => 'is-light',
            self::Medium   => 'is-warning',
            self::High     => 'is-danger',
            self::Critical => 'is-danger',
        };
    }
}
