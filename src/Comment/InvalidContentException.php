<?php declare(strict_types=1);

namespace App\Comment;

use InvalidArgumentException;

final class InvalidContentException extends InvalidArgumentException
{
    public const string REASON_EMPTY = 'empty';
    public const string REASON_TOO_LONG = 'too_long';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('Comment content rejected: ' . $reason);
    }
}
