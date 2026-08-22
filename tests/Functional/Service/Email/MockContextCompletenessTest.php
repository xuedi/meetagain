<?php declare(strict_types=1);

namespace Tests\Functional\Service\Email;

use App\Repository\EmailTemplateRepository;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailTemplateService;
use App\Service\Email\PreviewSweepService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MockContextCompletenessTest extends KernelTestCase
{
    public function testEveryMockContextSuppliesEveryTemplateVariableInEveryLocale(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $locales = $container->get(LanguageService::class)->getFilteredEnabledCodes();
        $variablesByIdentifier = $this->variablesByIdentifier($container->get(EmailTemplateRepository::class));

        // Act & Assert
        foreach ($container->get(PreviewSweepService::class)->typesByIdentifier() as $identifier => $emailType) {
            $variables = $variablesByIdentifier[$identifier] ?? null;
            static::assertNotNull($variables, sprintf('No seeded template row for "%s"', $identifier));

            foreach ($locales as $locale) {
                $context = $emailType->getDisplayMockData($locale)['context'];
                foreach ($variables as $variable) {
                    if ($variable === 'greeting') {
                        continue;
                    }
                    static::assertArrayHasKey(
                        $variable,
                        $context,
                        sprintf('"%s" mock context is missing "%s" in %s', $identifier, $variable, $locale),
                    );
                }
            }
        }
    }

    public function testEveryTemplateRendersWithoutLeavingAPlaceholder(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $templateService = $container->get(EmailTemplateService::class);
        $locales = $container->get(LanguageService::class)->getFilteredEnabledCodes();

        // Act & Assert
        foreach ($container->get(PreviewSweepService::class)->typesByIdentifier() as $identifier => $emailType) {
            foreach ($locales as $locale) {
                $context = $emailType->getDisplayMockData($locale)['context'] + ['greeting' => 'Hello,'];
                $content = $templateService->getTemplateContent($identifier, $locale);
                $rendered = $templateService->renderContent($content['subject'], $context)
                    . $templateService->renderContent($content['body'], $context);

                static::assertDoesNotMatchRegularExpression(
                    '/\{\{\s*\w+\s*\}\}/',
                    $rendered,
                    sprintf('"%s" leaves an unresolved placeholder in %s', $identifier, $locale),
                );
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function variablesByIdentifier(EmailTemplateRepository $repository): array
    {
        $variables = [];
        foreach ($repository->findAll() as $template) {
            $variables[(string) $template->getIdentifier()] = $template->getAvailableVariables() ?? [];
        }

        return $variables;
    }
}
