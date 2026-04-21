<?php declare(strict_types=1);

namespace App\Service\Email;

interface BlocklistCheckerInterface
{
    public function isBlocked(string $email): bool;
}
