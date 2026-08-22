<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Repository\EmailTemplateTranslationRepository;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
        private readonly LanguageService $languageService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Reset every existing translation back to the shipped default, discarding admin edits',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $overwrite = (bool) $input->getOption('overwrite');

        if ($overwrite) {
            new SymfonyStyle($input, $output)
                ->warning('Overwrite mode: every subject and body is reset to the shipped default. Wording typed into the admin UI is lost.');
        }

        $defaults = $this->templateService->getDefaultTemplates();
        $languages = $this->languageService->getEnabledCodes();

        $defaultsByLanguage = [];
        foreach ($languages as $languageCode) {
            $defaultsByLanguage[$languageCode] = $this->templateService->getDefaultTemplates($languageCode);
        }

        $created = 0;
        $translationsCreated = 0;
        $translationsOverwritten = 0;

        foreach ($defaults as $identifier => $data) {
            $template = $this->templateService->getTemplate($identifier);

            if (!$template instanceof EmailTemplate) {
                $template = new EmailTemplate();
                $template->setIdentifier($identifier);
                $template->setAvailableVariables($data['variables']);
                $template->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($template);
                ++$created;
                $output->writeln(sprintf('Created template "%s".', $identifier));
            } elseif ($overwrite) {
                $template->setAvailableVariables($data['variables']);
                $template->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($template);
            }

            foreach ($languages as $languageCode) {
                $existingTranslation = $this->translationRepo->findOneBy([
                    'emailTemplate' => $template->getId(),
                    'language' => $languageCode,
                ]);

                if ($existingTranslation !== null && !$overwrite) {
                    continue;
                }

                $langData = $defaultsByLanguage[$languageCode][$identifier];

                $translation = $existingTranslation ?? new EmailTemplateTranslation();
                $translation->setEmailTemplate($template);
                $translation->setLanguage($languageCode);
                $translation->setSubject($langData['subject']);
                $translation->setBody($langData['body']);
                $translation->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($translation);

                if ($existingTranslation !== null) {
                    ++$translationsOverwritten;
                    $output->writeln(sprintf('Overwrote translation for "%s" (%s).', $identifier, $languageCode));
                    continue;
                }

                ++$translationsCreated;
                $output->writeln(sprintf('Created translation for "%s" (%s).', $identifier, $languageCode));
            }
        }

        $this->em->flush();
        $output->writeln(sprintf(
            'Done. Created %d templates, %d translations, overwrote %d translations.',
            $created,
            $translationsCreated,
            $translationsOverwritten,
        ));

        return Command::SUCCESS;
    }
}
