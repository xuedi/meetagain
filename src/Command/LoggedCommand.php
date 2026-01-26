<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CommandExecutionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class LoggedCommand extends Command
{
    public function __construct(
        protected readonly CommandExecutionService $commandExecutionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName() ?? static::class;
        $log = $this->commandExecutionService->start($commandName);

        $bufferedOutput = new BufferedOutput();

        try {
            $exitCode = $this->doExecute($input, $output);

            $this->commandExecutionService->complete($log, $exitCode, $bufferedOutput->fetch() ?: null);

            return $exitCode;
        } catch (Throwable $e) {
            $this->commandExecutionService->fail($log, $e->getMessage());

            throw $e;
        }
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output): int;
}
