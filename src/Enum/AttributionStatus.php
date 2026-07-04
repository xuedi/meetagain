<?php declare(strict_types=1);

namespace App\Enum;

/**
 * Review state of an image's attribution credit. Derived from the Image's attribution
 * fields, never stored.
 */
enum AttributionStatus
{
    case Pending;
    case Provided;
    case NotRequired;
}
