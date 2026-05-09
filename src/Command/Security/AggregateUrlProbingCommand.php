<?php declare(strict_types=1);

namespace App\Command\Security;

use App\Service\Security\Incident\IncidentAggregator;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:security:aggregate-incidents',
    description: 'Run all incident sources (URL probing, access denied, rate limit, brute force) into logs_incident',
)]
class AggregateUrlProbingCommand extends Command
{
    public function __construct(
        private readonly IncidentAggregator $aggregator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->aggregator->aggregate() as $stats) {
            $output->writeln(sprintf(
                '%s: considered=%d ips=%d incidents=%d hasMore=%s',
                $stats->sourceKey,
                $stats->considered,
                $stats->ipsTouched,
                $stats->incidentsTouched,
                $stats->hasMore ? 'yes' : 'no',
            ));
        }

        return Command::SUCCESS;
    }
}
