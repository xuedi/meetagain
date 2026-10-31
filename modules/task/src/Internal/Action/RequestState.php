<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

enum RequestState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return 'admin_maintenance.state_' . $this->value;
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::Pending => 'is-warning is-light',
            self::Running => 'is-warning',
            self::Finished => 'is-light',
        };
    }
}
