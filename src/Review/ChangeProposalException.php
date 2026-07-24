<?php declare(strict_types=1);

namespace App\Review;

use RuntimeException;

/**
 * User-facing failure of a proposal action; the message is a translation key or a pre-translated
 * provider validation error, safe to flash.
 */
class ChangeProposalException extends RuntimeException {}
