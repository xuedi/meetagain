<?php declare(strict_types=1);

namespace Module\Task\Contract;

enum Status: string
{
    case ok = 'ok';
    case warning = 'warning';
    case error = 'error';
    case exception = 'exception';

    public function worst(self $other): self
    {
        $order = [self::ok, self::warning, self::error, self::exception];

        return array_search($this, $order, strict: true) >= array_search($other, $order, strict: true) ? $this : $other;
    }

    public function label(): string
    {
        return 'admin_task.status_' . $this->value;
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::ok => 'is-success',
            self::warning => 'is-warning',
            self::error => 'is-danger',
            self::exception => 'is-danger is-dark',
        };
    }
}
