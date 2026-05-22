<?php declare(strict_types=1);

namespace App\Command\Security;

use App\Service\Security\BlockedSessionStore;
use App\Service\Security\Provider\AbstractSecurityProvider;
use App\Service\Security\SecurityProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:security:reset-state',
    description: 'Reset SecurityService runtime state: clear blocked sessions/IPs and per-provider state. Use before attack-test scenarios.',
)]
final class SecurityResetStateCommand extends Command
{
    /**
     * @param iterable<SecurityProviderInterface> $providers
     */
    public function __construct(
        private readonly BlockedSessionStore $blockStore,
        #[AutowireIterator(SecurityProviderInterface::class)]
        private readonly iterable $providers,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('reset-incidents', null, InputOption::VALUE_NONE, 'Also DELETE all rows from logs_incident (destructive).');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->blockStore->clearAll();
        $output->writeln('Cleared BlockedSessionStore (sessions + IPs).');

        $cleared = 0;
        foreach ($this->providers as $provider) {
            if (!$provider instanceof AbstractSecurityProvider) {
                continue;
            }

            $provider->clearAllState();
            ++$cleared;
        }
        $output->writeln(sprintf('Cleared per-provider state for %d providers.', $cleared));

        if ($input->getOption('reset-incidents') === true) {
            $deleted = $this->em->getConnection()->executeStatement('DELETE FROM logs_incident');
            $output->writeln(sprintf('Deleted %d rows from logs_incident.', (int) $deleted));
        }

        return Command::SUCCESS;
    }
}
