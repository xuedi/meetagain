<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface ActionSettingsInterface
{
    /**
     * The value saved on the action's page for a remembered parameter, null while none is saved.
     */
    public function get(string $identifier, string $key): string|int|bool|null;
}
