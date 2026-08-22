<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\EmailInterface;
use App\Emails\SendingIdentity;
use App\Entity\EmailQueue;
use App\Entity\User;
use App\Filter\Email\AudienceFilterService;
use App\Repository\EmailQueueRepository;
use App\Service\Email\EmailService;
use App\Service\Email\EmailTemplateService;
use App\Service\Email\LayoutRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;

class CoreOnlySendingIdentityTest extends TestCase
{
    private const string SITE = 'Example Community';
    private const string HOST = 'https://example.org';

    public function testWithNoProviderRegisteredTheSnapshotIsTheOneCaptureBuilt(): void
    {
        // Arrange
        $captured = null;
        $em = $this->capturingEntityManager($captured);

        // Act
        $this->service($em)->enqueue($this->source(), $this->email(), []);

        // Assert
        static::assertSame(
            ['siteName' => self::SITE, 'siteUrl' => self::HOST],
            $captured->getContext()[LayoutRenderer::CONTEXT_KEY],
        );
    }

    public function testHostAndUrlResolveToTheConfiguredHost(): void
    {
        // Arrange
        $captured = null;
        $em = $this->capturingEntityManager($captured);

        // Act
        $this->service($em)->enqueue($this->source(), $this->email(), []);

        // Assert
        static::assertSame(self::HOST, $captured->getContext()['host']);
        static::assertSame('example.org', $captured->getContext()['url']);
    }

    public function testTheSignOffIsTheSiteNameRatherThanBlank(): void
    {
        // Arrange
        $captured = null;
        $em = $this->capturingEntityManager($captured);

        // Act
        $this->service($em)->enqueue($this->source(), $this->email(), []);

        // Assert
        static::assertSame(self::SITE, $captured->getContext()['greeting']);
    }

    public function testNoAttributionIsSetSoTheFooterKeepsItsPlainSentByLine(): void
    {
        // Arrange
        $captured = null;
        $em = $this->capturingEntityManager($captured);

        // Act
        $this->service($em)->enqueue($this->source(), $this->email(), []);

        // Assert
        static::assertArrayNotHasKey('attribution', $captured->getContext()[LayoutRenderer::CONTEXT_KEY]);
    }

    public function testWithNoAudienceFilterRegisteredEveryRecipientStillReceivesInstallationWideMail(): void
    {
        // Arrange
        $recipients = [new User()->setEmail('a@example.org'), new User()->setEmail('b@example.org')];

        // Act
        $audience = new AudienceFilterService([])->installationWideAudience($recipients);

        // Assert
        static::assertSame($recipients, $audience);
    }

    private function service(EntityManagerInterface $em): EmailService
    {
        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService->method('getTemplateContent')->willReturn(['subject' => 'Subject', 'body' => '<p>Body</p>']);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $content): string => $content);

        $identity = new SendingIdentity(siteName: self::SITE, siteUrl: self::HOST, greeting: self::SITE);

        $layoutRenderer = $this->createStub(LayoutRenderer::class);
        $layoutRenderer->method('captureIdentity')->willReturn($identity);
        $layoutRenderer->method('snapshot')->willReturn(['siteName' => self::SITE, 'siteUrl' => self::HOST]);
        $layoutRenderer->method('wrap')->willReturn('<html></html>');

        return new EmailService(
            transport: $this->createStub(TransportInterface::class),
            mailRepo: $this->createStub(EmailQueueRepository::class),
            em: $em,
            templateService: $templateService,
            layoutRenderer: $layoutRenderer,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
            identityProviders: [],
        );
    }

    private function capturingEntityManager(?EmailQueue &$captured): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with(static::callback(
            static function (EmailQueue $row) use (&$captured): bool {
                $captured = $row;

                return true;
            },
        ));

        return $em;
    }

    private function source(): EmailInterface
    {
        $source = $this->createStub(EmailInterface::class);
        $source->method('getIdentifier')->willReturn('welcome');

        return $source;
    }

    private function email(): TemplatedEmail
    {
        $email = new TemplatedEmail();
        $email->from(new Address('noreply@example.org'));
        $email->to('user@example.org');
        $email->locale('en');
        $email->context([]);

        return $email;
    }
}
