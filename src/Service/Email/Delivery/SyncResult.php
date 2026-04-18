<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

final readonly class SyncResult
{
    public function __construct(
        public bool $available,
        public int $updated,
        public int $checked,
    ) {}

    public static function unavailable(): self
    {
        return new self(false, 0, 0);
    }

    public static function success(int $updated, int $checked): self
    {
        return new self(true, $updated, $checked);
    }
}
