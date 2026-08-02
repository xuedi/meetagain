<?php declare(strict_types=1);

namespace App\Service\TownHall;

use InvalidArgumentException;

final class InvalidTopicException extends InvalidArgumentException
{
    public const string REASON_EMPTY_TITLE = 'empty_title';
    public const string REASON_TITLE_TOO_LONG = 'title_too_long';
    public const string REASON_TOO_DEEP = 'too_deep';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('Topic rejected: ' . $reason);
    }
}
