<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\EmailTemplate;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:email-templates:seed', description: 'Seeds default email templates if not present')]
class EmailTemplateSeedCommand extends Command
{
    public function __construct(
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $defaults = $this->templateService->getDefaultTemplates();
        $created = 0;

        foreach ($defaults as $identifier => $data) {
            $existing = $this->templateService->getTemplate($identifier);
            if ($existing instanceof EmailTemplate) {
                $output->writeln(sprintf('Template "%s" already exists, skipping.', $identifier));
                continue;
            }

            $template = new EmailTemplate();
            $template->setIdentifier($identifier);
            $template->setSubject($data['subject']);
            $template->setBody($data['body']);
            $template->setAvailableVariables($data['variables']);
            $template->setUpdatedAt(new DateTimeImmutable());

            $this->em->persist($template);
            ++$created;
            $output->writeln(sprintf('Created template "%s".', $identifier));
        }

        $this->em->flush();
        $output->writeln(sprintf('Done. Created %d templates.', $created));

        return Command::SUCCESS;
    }
}
