<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Tests\Stub\ContextEnricher;
use Module\Email\Tests\Stub\IdentityProvider;
use Module\Email\Tests\Stub\Origin;
use Module\Email\Tests\Stub\PushDispatcher;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EnqueueTest extends KernelTestCase
{
    use SeedsTemplates;

    private const string RECIPIENT = 'member@module-test.example';

    private TriggeredEmail $type;

    private PushDispatcher $push;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->type = self::getContainer()->get(TriggeredEmail::class);
        $this->push = self::getContainer()->get(PushDispatcher::class);
    }

    public function testTheIdentityOfTheClaimedOriginIsFrozenIntoTheRow(): void
    {
        // Act
        $row = $this->send(['origin' => new Origin()]);

        // Assert
        self::assertSame(IdentityProvider::SITE_NAME, $row->layout['siteName']);
        self::assertSame(IdentityProvider::SITE_URL, $row->context['host']);
        self::assertSame('stub.module-test.example', $row->context['url']);
        self::assertSame(IdentityProvider::GREETING, $row->context['greeting']);
    }

    public function testAnOriginNobodyClaimsFallsThroughToTheNextIdentityProvider(): void
    {
        // Act
        $row = $this->send([]);

        // Assert
        self::assertNotSame(IdentityProvider::SITE_NAME, $row->layout['siteName']);
    }

    public function testEveryEnricherAddsToTheStoredContext(): void
    {
        // Act
        $row = $this->send([]);

        // Assert
        self::assertSame('en', $row->context[ContextEnricher::KEY]);
    }

    public function testAPlainVariableIsEscapedAndADeclaredHtmlVariableIsNot(): void
    {
        // Act
        $row = $this->send(['name' => '<b>Ann</b>', 'itemsHtml' => '<li>one</li>']);

        // Assert
        self::assertSame('<p>&lt;b&gt;Ann&lt;/b&gt;</p><ul><li>one</li></ul>', $row->renderedBody);
        self::assertSame('Hello <b>Ann</b>', $row->subject);
    }

    public function testAQueuedMessagePingsEveryPushDispatcher(): void
    {
        // Act
        $this->send([]);

        // Assert
        self::assertSame([['identifier' => TriggeredEmail::IDENTIFIER, 'recipient' => self::RECIPIENT]], $this->push->pings);
    }

    public function testATypeThatOptsOutOfPushPingsNobody(): void
    {
        // Arrange
        $this->type->push = false;

        // Act
        $this->send([]);

        // Assert
        self::assertSame([], $this->push->pings);
    }

    public function testAThrowingPushDispatcherStillLeavesTheRowQueued(): void
    {
        // Arrange
        $this->push->throws = true;

        // Act
        $row = $this->send([]);

        // Assert
        self::assertSame(self::RECIPIENT, $row->recipient);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(array $context): SentEmail
    {
        self::getContainer()
            ->get(MailerInterface::class)
            ->send($this->type, [...$context, 'recipients' => [self::RECIPIENT]]);
        $rows = self::getContainer()->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER);
        self::assertCount(1, $rows);

        return $rows[0];
    }
}
