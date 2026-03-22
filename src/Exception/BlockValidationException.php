<?php declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class BlockValidationException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly array $errors,
        string $message = 'Block validation failed',
    ) {
        parent::__construct($message . ': ' . implode(', ', $errors));
    }
}
