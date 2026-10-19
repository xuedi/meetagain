<?php declare(strict_types=1);

namespace Module\Task\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface TaskInterface
{
    public function getIdentifier(): string;

    public function getCadence(): Cadence;

    public function getDescription(): string;

    public function run(): Result;
}
