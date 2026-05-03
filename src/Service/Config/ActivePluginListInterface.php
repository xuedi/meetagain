<?php declare(strict_types=1);

namespace App\Service\Config;

interface ActivePluginListInterface
{
    /**
     * Returns the keys of plugins active in the current request context.
     *
     * @return array<string>
     */
    public function getActiveList(): array;
}
