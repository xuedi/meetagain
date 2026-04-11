<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Seo\IndexNowService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:indexnow:submit', description: 'Submit all public URLs to IndexNow')]
class IndexNowSubmitCommand extends Command
{
    public function __construct(
        private readonly IndexNowService $indexNowService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->indexNowService->submit();
        $status = $result['status'];
        $host = $result['host'];

        match (true) {
            $status === 200 => $output->writeln("<info>[{$host}] Accepted (200)</info>"),
            $status === 202 => $output->writeln("<comment>[{$host}] Pending key validation (202)</comment>"),
            default => $output->writeln("<error>[{$host}] Failed: HTTP {$status}</error>"),
        };

        if ($status === 200 || $status === 202) {
            $this->indexNowService->recordSubmission();

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
