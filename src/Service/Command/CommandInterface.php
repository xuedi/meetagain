<?php declare(strict_types=1);

namespace App\Service\Command;

interface CommandInterface
{
    public function getCommand(): string;

    public function getParameter(): array;
}
