<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Service\EmailTemplateService;
use App\Service\LanguageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class EmailTemplateFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly EmailTemplateService $templateService,
        private readonly LanguageService $languageService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $languages = $this->languageService->getEnabledCodes();

        foreach ($this->templateService->getDefaultTemplates() as $identifier => $data) {
            $template = new EmailTemplate();
            $template->setIdentifier($identifier);
            $template->setAvailableVariables($data['variables']);
            $template->setUpdatedAt(new DateTimeImmutable());

            $manager->persist($template);

            // Create translations for all enabled languages with language-specific content
            foreach ($languages as $languageCode) {
                $langDefaults = $this->templateService->getDefaultTemplates($languageCode);
                $langData = $langDefaults[$identifier];

                $translation = new EmailTemplateTranslation();
                $translation->setEmailTemplate($template);
                $translation->setLanguage($languageCode);
                $translation->setSubject($langData['subject']);
                $translation->setBody($langData['body']);
                $translation->setUpdatedAt(new DateTimeImmutable());

                $manager->persist($translation);
            }
        }
        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
