<?php declare(strict_types=1);

namespace Module\Trust\Contract;

final readonly class ActionDescriptor
{
    public function __construct(
        public string $key,
        public string $label,
        public int $defaultPoints,
        public ?int $quantityCap = null,
    ) {}
}
