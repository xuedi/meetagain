<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Emails\EmailInterface;
use App\Entity\EmailQueue;
use App\Entity\EmailTemplate;
use App\Repository\EmailQueueRepository;
use App\Repository\EmailTemplateRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailService;
use App\Service\Email\LayoutRenderer;
use App\Service\Email\PreviewSweepService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class PreviewSweepServiceTest extends TestCase
{
    public function testRefusesToRunOutsideDevAndTest(): void
    {
        // Arrange
        $service = $this->makeService(environment: 'prod');

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refuses to run in "prod"');
        $service->sweep();
    }

    public function testThePassedOriginReachesEnqueueSoASweepCanRenderAnotherSitesIdentity(): void
    {
        // Arrange
        $origin = new stdClass();
        $seen = [];
        $queue = $this->createStub(EmailService::class);
        $queue->method('enqueue')->willReturnCallback(
            static function (EmailInterface $source, TemplatedEmail $email, array $context, bool $flush, ?object $passed) use (&$seen): bool {
                $seen[] = $passed;

                return true;
            },
        );
        $service = $this->makeService(emailService: $queue, locales: ['en']);

        // Act
        $service->sweep(origin: $origin);

        // Assert
        static::assertNotSame([], $seen);
        static::assertSame([$origin], array_unique($seen, SORT_REGULAR));
    }

    public function testASweepWithNoOriginLeavesTheTypeToAnswerForItself(): void
    {
        // Arrange
        $seen = [];
        $queue = $this->createStub(EmailService::class);
        $queue->method('enqueue')->willReturnCallback(
            static function (EmailInterface $source, TemplatedEmail $email, array $context, bool $flush, ?object $passed) use (&$seen): bool {
                $seen[] = $passed;

                return true;
            },
        );
        $service = $this->makeService(emailService: $queue, locales: ['en']);

        // Act
        $service->sweep();

        // Assert
        static::assertNotSame([], $seen);
        static::assertSame([null], array_unique($seen, SORT_REGULAR));
    }

    public function testEnqueuesOneMailPerTypeAndLocale(): void
    {
        // Arrange
        $queue = $this->createStub(EmailService::class);
        $recipients = [];
        $queue->method('enqueue')->willReturnCallback(
            static function (EmailInterface $source, TemplatedEmail $email) use (&$recipients): bool {
                $recipients[] = $email->getTo()[0]->getAddress();

                return true;
            },
        );
        $service = $this->makeService(emailService: $queue, locales: ['en', 'de']);

        // Act
        $result = $service->sweep();

        // Assert
        static::assertSame(4, $result->enqueued);
        static::assertSame([
            'welcome+de@preview.invalid',
            'announcement+de@preview.invalid',
            'welcome+en@preview.invalid',
            'announcement+en@preview.invalid',
        ], $recipients);
    }

    public function testCallerTagsBecomeExtraPlusAddressSegments(): void
    {
        // Arrange
        $queue = $this->createStub(EmailService::class);
        $recipients = [];
        $queue->method('enqueue')->willReturnCallback(
            static function (EmailInterface $source, TemplatedEmail $email) use (&$recipients): bool {
                $recipients[] = $email->getTo()[0]->getAddress();

                return true;
            },
        );
        $service = $this->makeService(emailService: $queue, locales: ['en']);

        // Act
        $service->sweep(['welcome'], recipientTags: ['weiqi-club']);

        // Assert
        static::assertSame(['welcome+en+weiqi-club@preview.invalid'], $recipients);
    }

    public function testNarrowsTheMatrixByTypeAndLanguage(): void
    {
        // Arrange
        $service = $this->makeService(locales: ['en', 'de', 'zh']);

        // Act
        $result = $service->sweep(['welcome'], ['de']);

        // Assert
        static::assertSame(['welcome'], $result->identifiers);
        static::assertSame(['de'], $result->locales);
        static::assertSame(1, $result->enqueued);
    }

    public function testUnknownTypeIsAnError(): void
    {
        // Arrange
        $service = $this->makeService();

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown email type "nope"');
        $service->sweep(['nope']);
    }

    public function testUnknownLanguageIsAnError(): void
    {
        // Arrange
        $service = $this->makeService(locales: ['en', 'de']);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown language "kl"');
        $service->sweep([], ['kl']);
    }

    public function testSubjectsAreTaggedWithIdentifierResolvedSiteAndLanguage(): void
    {
        // Arrange
        $row = $this->pendingRow('welcome+de@preview.invalid', 'Willkommen!', 'Weiqi Club');
        $service = $this->makeService(pending: [$row]);

        // Act
        $result = $service->sweep();

        // Assert
        static::assertTrue($result->tagged);
        static::assertSame('[welcome][Weiqi Club][de] Willkommen!', $row->getSubject());
    }

    public function testPlainSweepLeavesSubjectsUntouched(): void
    {
        // Arrange
        $row = $this->pendingRow('welcome+de@preview.invalid', 'Willkommen!', 'Weiqi Club');
        $service = $this->makeService(pending: [$row]);

        // Act
        $result = $service->sweep(tagSubjects: false);

        // Assert
        static::assertFalse($result->tagged);
        static::assertSame('Willkommen!', $row->getSubject());
    }

    public function testTaggingSkipsQueueRowsThatAreNotPartOfTheSweep(): void
    {
        // Arrange
        $row = $this->pendingRow('member@example.org', 'Willkommen!', 'Weiqi Club');
        $service = $this->makeService(pending: [$row]);

        // Act
        $service->sweep();

        // Assert
        static::assertSame('Willkommen!', $row->getSubject());
    }

    public function testATaggedSubjectStaysWithinTheColumnLimit(): void
    {
        // Arrange
        $row = $this->pendingRow('welcome+de@preview.invalid', str_repeat('a', 250), 'Weiqi Club');
        $service = $this->makeService(pending: [$row]);

        // Act
        $service->sweep();

        // Assert
        static::assertSame(255, mb_strlen((string) $row->getSubject()));
    }

    public function testTemplateWithNoEmailTypeBehindItIsReportedAsSkipped(): void
    {
        // Arrange
        $service = $this->makeService(templateIdentifiers: ['welcome', 'announcement', 'plugin_orphan']);

        // Act
        $result = $service->sweep();

        // Assert
        static::assertSame(['plugin_orphan'], $result->withoutType);
    }

    /**
     * @param list<string> $locales
     * @param list<string> $templateIdentifiers
     * @param list<EmailQueue> $pending
     */
    private function makeService(
        ?EmailService $emailService = null,
        array $locales = ['en'],
        array $templateIdentifiers = ['welcome', 'announcement'],
        string $environment = 'test',
        array $pending = [],
    ): PreviewSweepService {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredEnabledCodes')->willReturn($locales);

        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@example.org'));

        $templates = [];
        foreach ($templateIdentifiers as $identifier) {
            $templates[] = new EmailTemplate()->setIdentifier($identifier);
        }
        $templateRepo = $this->createStub(EmailTemplateRepository::class);
        $templateRepo->method('findAll')->willReturn($templates);

        $queueRepo = $this->createStub(EmailQueueRepository::class);
        $queueRepo->method('findBy')->willReturn($pending);

        return new PreviewSweepService(
            [$this->emailType('welcome'), $this->emailType('announcement')],
            $emailService ?? $this->createStub(EmailService::class),
            $templateRepo,
            $queueRepo,
            $this->createStub(EntityManagerInterface::class),
            $languageService,
            $config,
            $environment,
        );
    }

    private function pendingRow(string $recipient, string $subject, string $siteName): EmailQueue
    {
        return new EmailQueue()
            ->setRecipient($recipient)
            ->setSubject($subject)
            ->setLang('de')
            ->setTemplate('welcome')
            ->setContext([LayoutRenderer::CONTEXT_KEY => ['siteName' => $siteName]]);
    }

    private function emailType(string $identifier): EmailInterface
    {
        $type = $this->createStub(EmailInterface::class);
        $type->method('getIdentifier')->willReturn($identifier);
        $type->method('getDisplayMockData')->willReturnCallback(
            static fn(string $locale): array => ['subject' => $identifier, 'context' => ['lang' => $locale]],
        );

        return $type;
    }
}
