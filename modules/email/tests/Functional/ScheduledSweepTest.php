<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\DueContext;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Internal\SendScheduledEmailsService;
use Module\Email\Tests\Stub\ScheduledEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Output\BufferedOutput;

final class ScheduledSweepTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use SeedsTemplates;

    private const array RECIPIENTS = ['first@module-test.example', 'second@module-test.example'];

    private ScheduledEmail $type;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->type = self::getContainer()->get(ScheduledEmail::class);
        $this->type->due = [new DueContext(['name' => 'Ann'], self::RECIPIENTS)];
    }

    public function testEachRecipientOfADueContextGetsOneMessageAndTheContextIsMarked(): void
    {
        // Arrange
        self::mockTime('2031-03-04 12:00');

        // Act
        $message = $this->sweep();

        // Assert
        self::assertStringContainsString('2 emails queued', $message);
        self::assertSame(self::RECIPIENTS, $this->queuedRecipients());
        self::assertCount(1, $this->type->marked);
    }

    public function testNothingIsSentOutsideTheAllowedHours(): void
    {
        // Arrange
        self::mockTime('2031-03-04 23:00');

        // Act
        $message = $this->sweep();

        // Assert
        self::assertStringContainsString('outside allowed hours', $message);
        self::assertSame([], $this->queuedRecipients());
        self::assertSame([], $this->type->marked);
    }

    private function sweep(): string
    {
        $output = new BufferedOutput();
        self::getContainer()->get(SendScheduledEmailsService::class)->runCronTask($output);

        return $output->fetch();
    }

    /**
     * @return list<string>
     */
    private function queuedRecipients(): array
    {
        $rows = self::getContainer()->get(SendlogInterface::class)->list(template: ScheduledEmail::IDENTIFIER);

        return array_reverse(array_map(static fn(SentEmail $row): string => $row->recipient, $rows));
    }
}
