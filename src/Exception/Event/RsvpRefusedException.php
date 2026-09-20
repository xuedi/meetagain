<?php declare(strict_types=1);

namespace App\Exception\Event;

use App\Enum\RsvpRefusal;
use App\Exception\DomainException;

class RsvpRefusedException extends DomainException
{
    public function __construct(
        public readonly RsvpRefusal $reason,
    ) {
        parent::__construct($reason->value);
    }
}
