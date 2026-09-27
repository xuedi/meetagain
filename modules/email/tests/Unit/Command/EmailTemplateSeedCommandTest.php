<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Command;

use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Internal\Command\EmailTemplateSeedCommand;
use Module\Email\Internal\EmailTemplateService;
use Module\Email\Internal\Entity\EmailTemplate;
use Module\Email\Internal\Entity\EmailTemplateTranslation;
use Module\Email\Internal\Repository\EmailTemplateTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EmailTemplateSeedCommandTest extends TestCase
{
    public function testAnExistingTranslationIsLeftAloneWithoutTheFlag(): void
    {
        // Arrange
        $existing = new EmailTemplateTranslation();
        $existing->setSubject('Subject an admin typed');
        $existing->setBody('<p>Body an admin typed</p>');
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $tester = new CommandTester($this->command($emMock, $existing));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame('Subject an admin typed', $existing->getSubject());
        static::assertSame('<p>Body an admin typed</p>', $existing->getBody());
        static::assertStringContainsString('overwrote 0 translations', $tester->getDisplay());
    }

    public function testTheFlagResetsAnExistingTranslationToTheShippedDefault(): void
    {
        // Arrange
        $existing = new EmailTemplateTranslation();
        $existing->setSubject('Subject an admin typed');
        $existing->setBody('<p>Body an admin typed</p>');
        $tester = new CommandTester($this->command($this->createStub(EntityManagerInterface::class), $existing));

        // Act
        $exitCode = $tester->execute(['--overwrite' => true]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame('Shipped subject', $existing->getSubject());
        static::assertSame('<p>Shipped body</p>', $existing->getBody());
        static::assertStringContainsString('overwrote 1 translations', $tester->getDisplay());
    }

    public function testTheFlagWarnsThatAdminWordingIsLost(): void
    {
        // Arrange
        $tester = new CommandTester($this->command($this->createStub(EntityManagerInterface::class), new EmailTemplateTranslation()));

        // Act
        $tester->execute(['--overwrite' => true]);

        // Assert
        static::assertStringContainsString('Wording typed into the admin UI is lost', $tester->getDisplay());
    }

    private function command(EntityManagerInterface $em, ?EmailTemplateTranslation $existing): EmailTemplateSeedCommand
    {
        $template = new EmailTemplate();
        $template->setIdentifier('welcome');

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService->method('getTemplate')->willReturn($template);
        $templateService
            ->method('getDefaultTemplates')
            ->willReturn([
                'welcome' => [
                    'subject' => 'Shipped subject',
                    'body' => '<p>Shipped body</p>',
                    'variables' => ['host'],
                ],
            ]);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn(['en']);

        $translationRepo = $this->createStub(EmailTemplateTranslationRepository::class);
        $translationRepo->method('findOneBy')->willReturn($existing);

        return new EmailTemplateSeedCommand($templateService, $em, $languageService, $translationRepo);
    }
}
