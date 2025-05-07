<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;

readonly class JustService
{
    public function __construct(private string $kernelProjectDir)
    {
    }

    public function command(string $command): string
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('action');

        $process = new Process(['just', $command]);
        $process->setWorkingDirectory($this->kernelProjectDir);
        $process->enableOutput();
        $process->start();
        $process->wait();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (string)$stopwatch->stop('action');
    }
}
