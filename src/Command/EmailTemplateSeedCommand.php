<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Enum\EmailType;
use App\Repository\EmailTemplateTranslationRepository;
use App\Service\EmailTemplateService;
use App\Service\LanguageService;
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
        private readonly LanguageService $languageService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $defaults = $this->templateService->getDefaultTemplates();
        $languages = $this->languageService->getEnabledCodes();
        $created = 0;
        $translationsCreated = 0;

        foreach ($defaults as $identifier => $data) {
            $emailType = EmailType::from($identifier);
            $template = $this->templateService->getTemplate($emailType);

            if (!$template instanceof EmailTemplate) {
                // Create new template
                $template = new EmailTemplate();
                $template->setIdentifier($identifier);
                $template->setAvailableVariables($data['variables']);
                $template->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($template);
                ++$created;
                $output->writeln(sprintf('Created template "%s".', $identifier));
            }

            // Ensure translations exist for all enabled languages
            foreach ($languages as $languageCode) {
                $existingTranslation = $this->translationRepo->findOneBy([
                    'emailTemplate' => $template->getId(),
                    'language' => $languageCode,
                ]);

                if ($existingTranslation !== null) {
                    continue;
                }

                // Get language-specific defaults
                $langDefaults = $this->templateService->getDefaultTemplates($languageCode);
                $langData = $langDefaults[$identifier];

                $translation = new EmailTemplateTranslation();
                $translation->setEmailTemplate($template);
                $translation->setLanguage($languageCode);
                $translation->setSubject($langData['subject']);
                $translation->setBody($langData['body']);
                $translation->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($translation);
                ++$translationsCreated;
                $output->writeln(sprintf('Created translation for "%s" (%s).', $identifier, $languageCode));
            }
        }

        $this->em->flush();
        $output->writeln(sprintf('Done. Created %d templates and %d translations.', $created, $translationsCreated));

        return Command::SUCCESS;
    }
}
