<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Repository\EmailQueueRepository;
use App\Service\Email\EmailService;
use App\Service\Config\LanguageService;
use App\Service\Email\LayoutRenderer;
use App\Service\Email\PreviewSweepService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EmailPreviewCommandTest extends KernelTestCase
{
    public function testSweepEnqueuesOneWrappedMailPerTypeAndLanguage(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $identifiers = $container->get(PreviewSweepService::class)->availableIdentifiers();
        $tester = $this->commandTester();

        // Act
        $tester->execute(['--lang' => ['en', 'de'], '--type' => [$identifiers[0], $identifiers[1]]]);

        // Assert
        $tester->assertCommandIsSuccessful();
        $rows = $this->sweptRows($container->get(EmailQueueRepository::class));
        static::assertCount(4, $rows);

        $layoutRenderer = $container->get(LayoutRenderer::class);
        foreach ($rows as $row) {
            static::assertArrayHasKey(LayoutRenderer::CONTEXT_KEY, $row->getContext(), 'the layout snapshot is taken at enqueue');
            static::assertStringContainsString('<html', $layoutRenderer->wrap($row)->html);
        }
    }

    public function testSweepLeavesTheQueuePendingForCronToDispatch(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $identifier = $container->get(PreviewSweepService::class)->availableIdentifiers()[0];
        $repository = $container->get(EmailQueueRepository::class);

        // Act
        $this->commandTester()->execute(['--lang' => ['en'], '--type' => [$identifier]]);

        // Assert
        $queued = $this->sweptRows($repository);
        static::assertCount(1, $queued);
        static::assertSame(EmailQueueStatus::Pending, $queued[0]->getStatus());

        // Act
        $container->get(EmailService::class)->sendQueue();

        // Assert
        static::assertSame(EmailQueueStatus::Sent, $this->sweptRows($repository)[0]->getStatus());
    }

    public function testSubjectsCarryTheDebugTagUnlessPlainIsPassed(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $identifier = $container->get(PreviewSweepService::class)->availableIdentifiers()[0];
        $repository = $container->get(EmailQueueRepository::class);

        // Act
        $this->commandTester()->execute(['--lang' => ['de'], '--type' => [$identifier]]);
        $tagged = $this->sweptRows($repository)[0];

        $this->commandTester()->execute(['--lang' => ['de'], '--type' => [$identifier], '--plain' => true]);
        $plain = $this->sweptRows($repository)[1];

        // Assert
        static::assertMatchesRegularExpression(
            sprintf('/^\[%s\]\[.+\]\[de\] \S/', preg_quote($identifier, '/')),
            (string) $tagged->getSubject(),
        );
        static::assertStringStartsNotWith('[', (string) $plain->getSubject());
        static::assertStringEndsWith((string) $plain->getSubject(), (string) $tagged->getSubject());
    }

    public function testSweepCoversEveryTypeInEveryEnabledLanguage(): void
    {
        // Arrange
        self::bootKernel();
        $container = static::getContainer();
        $identifiers = $container->get(PreviewSweepService::class)->availableIdentifiers();
        $locales = $container->get(LanguageService::class)->getFilteredEnabledCodes();
        $tester = $this->commandTester();

        // Act
        $tester->execute([]);

        // Assert
        $tester->assertCommandIsSuccessful();
        static::assertCount(count($identifiers) * count($locales), $this->sweptRows($container->get(EmailQueueRepository::class)));
    }

    public function testUnknownLanguageFails(): void
    {
        // Arrange
        self::bootKernel();
        $tester = $this->commandTester();

        // Act
        $exitCode = $tester->execute(['--lang' => ['kl']]);

        // Assert
        static::assertSame(1, $exitCode);
        static::assertStringContainsString('Unknown language "kl"', $tester->getDisplay());
    }

    public function testUnknownTypeFails(): void
    {
        // Arrange
        self::bootKernel();
        $tester = $this->commandTester();

        // Act
        $exitCode = $tester->execute(['--type' => ['not_a_template']]);

        // Assert
        static::assertSame(1, $exitCode);
        static::assertStringContainsString('Unknown email type "not_a_template"', $tester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find('app:email:preview'));
    }

    /**
     * @return list<EmailQueue>
     */
    private function sweptRows(EmailQueueRepository $repository): array
    {
        return array_values(array_filter(
            $repository->findAll(),
            static fn(EmailQueue $row): bool => str_ends_with((string) $row->getRecipient(), '@preview.invalid'),
        ));
    }
}
