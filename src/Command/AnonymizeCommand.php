<?php declare(strict_types=1);

namespace App\Command;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Context;
use App\Anonymization\Coverage;
use App\DependencyInjection\SeesInactivePlugins;
use App\Entity\User;
use App\Repository\ConfigRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:data:anonymize', description: 'Rewrites every piece of personal data in this instance: names, addresses, texts, traces and images')]
#[SeesInactivePlugins]
class AnonymizeCommand extends Command
{
    public const string PASSWORD = '1234';

    /**
     * @param iterable<AnonymizerInterface> $anonymizers
     */
    public function __construct(
        private readonly ConfigRepository $configRepo,
        private readonly UserRepository $userRepo,
        private readonly EntityManagerInterface $em,
        private readonly Coverage $coverage,
        private readonly UserPasswordHasherInterface $hasher,
        #[AutowireIterator(AnonymizerInterface::class)]
        private readonly iterable $anonymizers,
        #[Autowire(env: 'default::ANONYMIZE_HOST')]
        private readonly ?string $allowedHost,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host this instance is configured for (the setting website_url)');
        $this->addOption('erase-personal-data', null, InputOption::VALUE_NONE, 'Confirms that every account, text and image is rewritten for good');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $allowed = trim((string) $this->allowedHost);
        if ($allowed === '') {
            $io->error('This instance does not allow anonymization. Nothing was changed.');

            return Command::FAILURE;
        }

        $host = trim((string) $input->getOption('host'));
        $configured = $this->configRepo->findOneBy(['name' => 'website_url'])?->getValue() ?? '';
        if ($host === '' || $host !== $configured || $host !== $allowed) {
            $io->error('--host does not match this instance. Nothing was changed.');

            return Command::FAILURE;
        }

        if ($input->getOption('erase-personal-data') !== true) {
            $io->error('Pass --erase-personal-data to confirm. Nothing was changed.');

            return Command::FAILURE;
        }

        $problems = $this->coverage->problems();
        if ($problems !== []) {
            $io->error('Not every table and column is claimed by an anonymizer. Nothing was changed.');
            $io->listing($problems);

            return Command::FAILURE;
        }

        $context = new Context($this->hasher->hashPassword(new User(), self::PASSWORD), static function (string $line) use ($io): void {
            $io->writeln('  ' . $line);
        });
        $anonymizers = iterator_to_array($this->anonymizers, false);
        usort($anonymizers, static fn(AnonymizerInterface $a, AnonymizerInterface $b): int => $a->getOrder() <=> $b->getOrder());
        foreach ($anonymizers as $anonymizer) {
            $io->writeln($anonymizer::class);
            $anonymizer->anonymize($context);
        }

        $this->em->clear();
        $io->success(sprintf('Personal data rewritten on %s. Every account signs in with the password "%s".', $host, self::PASSWORD));
        $io->listing(array_map(static fn(User $admin): string => sprintf('admin: %s', $admin->getEmail()), $this->userRepo->findAdminUsers()));

        return Command::SUCCESS;
    }
}
