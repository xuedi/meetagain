<?php declare(strict_types=1);

namespace App\Enum;

enum CronTaskStatus: string
{
    case ok        = 'ok';
    case warning   = 'warning';
    case error     = 'error';
    case exception = 'exception';

    public function worst(self $other): self
    {
        $order = [self::ok, self::warning, self::error, self::exception];

        return array_search($this, $order, strict: true) >= array_search($other, $order, strict: true)
            ? $this
            : $other;
    }
}
