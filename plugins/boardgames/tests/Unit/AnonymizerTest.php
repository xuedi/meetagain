<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit;

use Plugin\Boardgames\Anonymizer;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class AnonymizerTest extends AnonymizerTestCase
{
    public function testShelfNotesAndBringRequestsGetFillerWhileTheCatalogStays(): void
    {
        // Arrange
        $subject = new Anonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject);
        self::assertSame([], $this->statementsOn('plg_boardgames_game'));
    }
}
