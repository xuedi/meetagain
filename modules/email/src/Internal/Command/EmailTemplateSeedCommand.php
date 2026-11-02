<?php declare(strict_types=1);

namespace Module\Email\Internal\Command;

use Module\Email\Internal\TemplateSeedAction;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:email-templates:seed', description: 'Seeds default email templates if not present')]
class EmailTemplateSeedCommand extends Command
{
    public function __construct(
        private readonly TemplateSeedAction $action,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Reset every existing translation back to the shipped default, discarding admin edits');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $overwrite = (bool) $input->getOption('overwrite');

        if ($overwrite) {
            new SymfonyStyle($input, $output)->warning(
                'Overwrite mode: every subject and body is reset to the shipped default. Wording typed into the admin UI is lost.',
            );
        }

        $seeded = $this->action->seed($overwrite);
        foreach ($seeded['templates'] as $identifier) {
            $output->writeln(sprintf('Created template "%s".', $identifier));
        }
        foreach ($seeded['translations'] as $translation) {
            $output->writeln(sprintf('Created translation for %s.', $translation));
        }
        foreach ($seeded['overwritten'] as $translation) {
            $output->writeln(sprintf('Overwrote translation for %s.', $translation));
        }

        $output->writeln(sprintf(
            'Done. Created %d templates, %d translations, overwrote %d translations.',
            count($seeded['templates']),
            count($seeded['translations']),
            count($seeded['overwritten']),
        ));

        return Command::SUCCESS;
    }
}
