<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization\Anonymizer;

use App\Anonymization\Anonymizer\TracesAnonymizer;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\HealthInterface;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class TracesAnonymizerTest extends AnonymizerTestCase
{
    public function testLogsAreEmptiedAndTheModulesForgetTheirPeople(): void
    {
        // Arrange
        $sendlog = $this->createMock(SendlogInterface::class);
        $sendlog->expects($this->once())->method('forgetAll')->willReturn(3);
        $blocklist = $this->createMock(BlocklistInterface::class);
        $blocklist->expects($this->once())->method('forgetAll')->willReturn(1);
        $actionQueue = $this->createMock(ActionQueueInterface::class);
        $actionQueue->expects($this->once())->method('forgetRequesters');
        $health = $this->createMock(HealthInterface::class);
        $health->expects($this->once())->method('discardFailed')->willReturn(0);
        $subject = new TracesAnonymizer($this->scrubber(), $sendlog, $blocklist, $actionQueue, $health);

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject, ['mod_email_queue', 'mod_email_blocklist', 'mod_task_message', 'mod_task_action_request']);
    }

    public function testRequestTracesAndCopiedNamesAreScrubbedFromActivityMeta(): void
    {
        // Arrange
        $subject = new TracesAnonymizer(
            $this->scrubber(),
            $this->createStub(SendlogInterface::class),
            $this->createStub(BlocklistInterface::class),
            $this->createStub(ActionQueueInterface::class),
            $this->createStub(HealthInterface::class),
        );

        // Act
        $subject->anonymize($this->context());

        // Assert
        $meta = $this->statementsOn('activity');
        self::assertSame(['$.ip', '$.user_agent'], [$meta[0][1]['path'], $meta[1][1]['path']]);
        self::assertSame('core.changed_username', $meta[2][1]['type']);
        self::assertStringContainsString('SELECT t.title FROM topic t', $meta[3][0]);
    }
}
