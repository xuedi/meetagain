<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Internal\Retention;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RetentionTest extends KernelTestCase
{
    use SeedsTemplates;

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testASentMailPastTheBodyRetentionKeepsItsRowButLosesItsBodyAndContext(): void
    {
        // Arrange
        $this->mail('old-sent', QueueStatus::Sent, Retention::EMAIL_BODY_RETENTION_DAYS + 1);
        $this->mail('fresh-sent', QueueStatus::Sent, 1);

        // Act
        self::getContainer()->get(Retention::class)->run();

        // Assert
        self::assertSame([null, '[]'], $this->bodyAndContext('old-sent'));
        self::assertNotNull($this->bodyAndContext('fresh-sent')[0]);
    }

    public function testALateMailKeepsItsBodySoItCanStillBeSent(): void
    {
        // Arrange
        $this->mail('late', QueueStatus::Late, Retention::EMAIL_BODY_RETENTION_DAYS + 1);

        // Act
        self::getContainer()->get(Retention::class)->run();

        // Assert
        self::assertNotNull($this->bodyAndContext('late')[0]);
    }

    public function testFinishedMailsPastTheRowRetentionAreDeletedAndAPendingOneNever(): void
    {
        // Arrange
        $age = Retention::EMAIL_ROW_RETENTION_DAYS + 1;
        $this->mail('sent', QueueStatus::Sent, $age);
        $this->mail('late', QueueStatus::Late, $age);
        $this->mail('failed', QueueStatus::Failed, $age);
        $this->mail('pending', QueueStatus::Pending, $age);

        // Act
        $result = self::getContainer()->get(Retention::class)->run();

        // Assert
        self::assertSame(['pending@module-test.example'], $this->recipients());
        self::assertStringContainsString('rows_deleted: 3', (string) $result->message);
    }

    private function mail(string $name, QueueStatus $status, int $ageDays): void
    {
        $recipient = $name . '@module-test.example';
        self::getContainer()
            ->get(MailerInterface::class)
            ->send(self::getContainer()->get(TriggeredEmail::class), ['recipients' => [$recipient]]);
        $this->connection->update(
            'mod_email_queue',
            ['status' => $status->value, 'created_at' => new DateTimeImmutable(sprintf('-%d days', $ageDays))],
            ['recipient' => $recipient],
            ['created_at' => Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function bodyAndContext(string $name): array
    {
        $row = $this->connection->fetchAssociative('SELECT rendered_body, context FROM mod_email_queue WHERE recipient = ?', [$name . '@module-test.example']);
        self::assertIsArray($row);

        return [$row['rendered_body'], (string) $row['context']];
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        return array_map(strval(...), $this->connection->fetchFirstColumn('SELECT recipient FROM mod_email_queue ORDER BY recipient'));
    }
}
