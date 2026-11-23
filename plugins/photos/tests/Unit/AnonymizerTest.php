<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit;

use Plugin\Photos\Activity\Messages\PhotoAdded;
use Plugin\Photos\Anonymizer;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class AnonymizerTest extends AnonymizerTestCase
{
    public function testPhotoTextsAndCameraDataGoAndTheActivityTitleFollows(): void
    {
        // Arrange
        $subject = new Anonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject);
        $activity = $this->statementsOn('activity');
        self::assertCount(1, $activity);
        self::assertSame(PhotoAdded::TYPE, $activity[0][1]['added']);
    }
}
