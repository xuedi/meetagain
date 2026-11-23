<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization\Anonymizer;

use App\Anonymization\Anonymizer\ReferenceDataAnonymizer;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class ReferenceDataAnonymizerTest extends AnonymizerTestCase
{
    public function testTheSettingsThatNameAnAddressGetAPlaceholder(): void
    {
        // Arrange
        $subject = new ReferenceDataAnonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject);
        self::assertSame(
            [
                ['security_contact',  'security@example.invalid'],
                ['email_sender_mail', 'noreply@example.invalid'],
            ],
            array_map(static fn(array $statement): array => [$statement[1]['name'], $statement[1]['address']], $this->statementsOn('config')),
        );
    }
}
