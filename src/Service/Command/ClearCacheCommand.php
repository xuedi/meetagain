<?php declare(strict_types=1);

namespace App\Service\Command;

readonly class ClearCacheCommand implements CommandInterface
{
    public function getCommand(): string
    {
        return 'cache:clear';
    }

    public function getParameter(): array
    {
        return [
            'command' => $this->getCommand(),
            '--no-warmup' => true,
        ];
    }
}
