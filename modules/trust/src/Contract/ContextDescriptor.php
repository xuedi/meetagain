<?php declare(strict_types=1);

namespace Module\Trust\Contract;

final readonly class ContextDescriptor
{
    public function __construct(
        public string $context,
        public string $label,
        public ?string $returnUrl = null,
    ) {}
}
