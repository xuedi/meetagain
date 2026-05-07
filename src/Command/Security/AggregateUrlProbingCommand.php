<?php declare(strict_types=1);

namespace App\Command\Security;

use App\Service\Security\UrlProbingAggregator;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:security:aggregate-url-probing',
    description: 'Roll raw 404 firehose rows into URL-probing incident records',
)]
class AggregateUrlProbingCommand extends Command
{
    public function __construct(
        private readonly UrlProbingAggregator $aggregator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stats = $this->aggregator->aggregate();
        $output->writeln(sprintf(
            'Considered %d raw rows across %d IPs, created %d incidents (%d dropped as below threshold)',
            $stats['considered'],
            $stats['ipsProcessed'],
            $stats['incidents'],
            $stats['dropped'],
        ));

        return Command::SUCCESS;
    }
}
