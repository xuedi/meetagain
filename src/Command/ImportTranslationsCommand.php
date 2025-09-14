<?php declare(strict_types=1);

namespace App\Command;

use App\Service\TranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:translation:import', description: 'imports online translations for local development')]
class ImportTranslationsCommand extends Command
{
    public function __construct(
        private readonly TranslationService $translationService,
    ) {
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
        $this->translationService->importForLocalDevelopment($input->getArgument('url'));

        return Command::SUCCESS;
    }
}
