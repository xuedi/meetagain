<?php declare(strict_types=1);

namespace Module\Email\Contract;

/**
 * Marks a mail type a recipient is waiting for. The queue claims rows of these types before any other row.
 */
interface UrgentEmailInterface extends EmailInterface {}
