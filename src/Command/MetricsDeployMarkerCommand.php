<?php declare(strict_types=1);

namespace App\Command;

use App\Metrics\Point;
use App\Metrics\Recorder;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:metrics:deploy-marker', description: 'Send a deploy marker to the metrics collector')]
class MetricsDeployMarkerCommand extends Command
{
    public function __construct(
        private readonly Recorder $recorder,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('rev', null, InputOption::VALUE_REQUIRED, 'Revision that was deployed', 'unknown');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->recorder->isEnabled()) {
            $output->writeln('Metrics are disabled, no marker sent.');

            return Command::SUCCESS;
        }

        $this->recorder->add(new Point('deploy', ['value' => 1], ['rev' => (string) $input->getOption('rev')]));
        $this->recorder->flush();
        $output->writeln('Deploy marker sent.');

        return Command::SUCCESS;
    }
}
