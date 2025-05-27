<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\UserStatus;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cleanup', description: 'does certain cleanup tasks',)]
class CleanupCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'Url of translation API');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // delete users that never finished registration
        $userList = $this->userRepository->findBy([
            'status' => UserStatus::Registered,
            'createdAt' => ['<=', new DateTime('-7 days')] // check if cleanly possible in findBy
        ]);
        dump($userList);
        return Command::FAILURE; // TODO: stop here for now
        foreach ($userList as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
