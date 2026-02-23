<?php declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface CronTaskInterface
{
    public function runCronTask(OutputInterface $output): void;
}
