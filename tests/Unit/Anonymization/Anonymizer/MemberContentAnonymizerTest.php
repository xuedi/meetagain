<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization\Anonymizer;

use App\Anonymization\Anonymizer\MemberContentAnonymizer;
use App\Anonymization\Scrubber;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class MemberContentAnonymizerTest extends AnonymizerTestCase
{
    public function testEveryClaimedColumnIsWritten(): void
    {
        // Arrange
        $subject = new MemberContentAnonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject);
    }

    public function testFreeTextIsReplacedByFillerOfTheSameLength(): void
    {
        // Arrange
        $subject = new MemberContentAnonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        [$sql, $params] = $this->statementsOn('comment')[0];
        self::assertStringContainsString(
            'SUBSTRING(REPEAT(:filler, CEIL(CHAR_LENGTH(`content`) / :length) + 1), 1 + MOD(id, :length), CHAR_LENGTH(`content`))',
            $sql,
        );
        self::assertSame(Scrubber::FILLER, $params['filler']);
    }
}
