<?php declare(strict_types=1);

namespace App\Anonymization;

use Closure;

final readonly class Context
{
    /**
     * @param Closure(string): void $reporter
     */
    public function __construct(
        public string $passwordHash,
        private Closure $reporter,
    ) {}

    public function report(string $line): void
    {
        ($this->reporter)($line);
    }
}
